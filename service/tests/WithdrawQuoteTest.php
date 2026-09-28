<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WithdrawController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 提现报价与限额（WithdrawController::withdrawQuote / exceedsLimit / withdrawLevel）。
 *
 * 覆盖背景：这三段逻辑原先内联在 applyLocked()/apply() 内，全仓零测试 —— 一批
 * 「在测试里把公式重算一遍」的假用例被删除后暴露出来的真实缺口。它们是钱的路径：
 *   - 手续费算错 → 用户实收少/多，平台账对不上（actual_amount 直接进 fiat_amount 列）
 *   - 单笔上下限算错 → 限额形同虚设，或正常提现被拦死
 *   - 日/月累计限额算错 → 同一用户可突破限额反复提现
 *   - 自动审核阈值算错 → 免风控、免人工直接放行（阈值判定与风控合取，此处只钉阈值那一段）
 *
 * 断言全部为精确值（字符串级别），不用「大于/小于」这种会随实现漂移的宽松断言。
 * 纯计算、不触库：三个函数输入显式，不读 $request/DB/Redis。
 */
class WithdrawQuoteTest extends TestCase
{
    /** 默认条款：与 install.sql 的 withdraw_limit 首行同形（0 = 不限制） */
    private static function terms(array $override = []): array
    {
        return $override + [
            'single_min'             => '0.0001',
            'single_max'             => '0',
            'auto_approve_threshold' => '0',
            'fee_pct'                => '0',
            'fee_max'                => '0',
        ];
    }

    /** @return array<string, mixed> */
    private static function quote(string $amount, array $terms, string $vipDiscount = '0'): array
    {
        return (new ReflectionMethod(WithdrawController::class, 'withdrawQuote'))
            ->invoke(null, $amount, $terms, $vipDiscount);
    }

    private static function exceeds(string $usedSum, string $amount, string $limit): bool
    {
        return (new ReflectionMethod(WithdrawController::class, 'exceedsLimit'))
            ->invoke(null, $usedSum, $amount, $limit);
    }

    /* ---------------- 单笔最小/最大限额 ---------------- */

    /** @return array<string, array{string, string, string, bool, ?string}> */
    public static function minMaxCases(): array
    {
        // 金额, 单笔下限, 单笔上限（'0'=不限）, 是否放行, 拒绝文案后缀
        return [
            '恰好等于下限'       => ['10', '10', '0', true, null],
            '低于下限 0.0001'    => ['9.9999', '10', '0', false, 'below minimum'],
            '下限为 0 的零金额'  => ['0', '0', '0', true, null],
            '负金额'             => ['-5', '0.0001', '0', false, 'below minimum'],
            '负下限与零金额'     => ['0', '-1', '0', true, null],
            '恰好等于上限'       => ['100', '0.0001', '100', true, null],
            '超出上限 0.0001'    => ['100.0001', '0.0001', '100', false, 'exceeds maximum'],
            '上限为 0 即不限制'  => ['999999999999999.9999', '0.0001', '0', true, null],
            '上下限相等且恰好命中' => ['50', '50', '50', true, null],
            '上下限相等且越界'   => ['50.0001', '50', '50', false, 'exceeds maximum'],
        ];
    }

    #[Test]
    #[DataProvider('minMaxCases')]
    public function singleMinAndMaxBoundaries(string $amount, string $min, string $max, bool $pass, ?string $reason): void
    {
        $quote = self::quote($amount, self::terms(['single_min' => $min, 'single_max' => $max]));

        if ($pass) {
            $this->assertNull($quote['error'], "{$amount} 应放行");
            $this->assertNull($quote['http']);
            return;
        }

        $this->assertNotNull($quote['error']);
        $this->assertSame(400, $quote['http'], '限额拒绝一律 400');
        $this->assertStringContainsString($reason, $quote['error']);
    }

    /** 拒绝文案是 API 契约（客户端按 code 判定），逐字钉住 */
    #[Test]
    public function rejectionMessagesAreByteExact(): void
    {
        $below = self::quote('9.9999', self::terms(['single_min' => '10']));
        $this->assertSame('Amount below minimum withdrawal limit', $below['error']);

        $above = self::quote('11', self::terms(['single_max' => '10']));
        $this->assertSame('Amount exceeds maximum withdrawal limit', $above['error']);

        // 驳回路径不应再暴露费用字段给人误读（fee 保持初值、actual_amount 原样回显）
        $this->assertSame('0', $below['fee']);
        $this->assertSame('9.9999', $below['actual_amount']);
    }

    /* ---------------- 手续费：费率 / VIP 折扣 / 封顶 ---------------- */

    #[Test]
    public function feeIsPercentOfAmountAtFourDecimals(): void
    {
        $quote = self::quote('100', self::terms(['fee_pct' => '2.5']));

        $this->assertSame('2.5000', $quote['fee'], '100 × 2.5%');
        $this->assertSame('97.5000', $quote['actual_amount']);
        $this->assertNull($quote['error']);
    }

    /** 费率 0 / 封顶 0 = 免手续费，且 fee 必须是字符串 '0'（不是 '0.0000'）——历史线格式 */
    #[Test]
    public function zeroFeePercentageSkipsFeeEntirely(): void
    {
        foreach ([['0', '0'], ['0', '5']] as [$pct, $max]) {
            $quote = self::quote('100', self::terms(['fee_pct' => $pct, 'fee_max' => $max]));
            $this->assertSame('0', $quote['fee'], "fee_pct={$pct} 时不收手续费");
            $this->assertSame('100.0000', $quote['actual_amount'], '实收 = 全额（bcsub 补足 4 位）');
        }
    }

    #[Test]
    public function vipDiscountScalesTheFeePercentage(): void
    {
        $terms = self::terms(['fee_pct' => '2.5']);

        $this->assertSame('2.5000', self::quote('100', $terms, '0')['fee'], '无折扣');
        $this->assertSame('1.2500', self::quote('100', $terms, '0.5')['fee'], '五折折扣');
        $this->assertSame('2.2500', self::quote('100', $terms, '0.1')['fee'], '九折折扣');
        $this->assertSame('0.0000', self::quote('100', $terms, '1')['fee'], '全免');
    }

    /**
     * 折扣率 > 1（脏数据/配置错误）会把有效费率算成负数 —— 必须被钳到 0，
     * 否则 bcmul 出负手续费、actual_amount 反而大于提交值（平台倒贴钱）。
     */
    #[Test]
    public function negativeEffectiveFeePercentageIsClampedToZero(): void
    {
        $quote = self::quote('100', self::terms(['fee_pct' => '2.5']), '2');

        $this->assertSame('0.0000', $quote['fee'], 'bcmul 先得 -2.5000，钳到 0 后再乘金额');
        $this->assertSame('100.0000', $quote['actual_amount'], '实收不得高于提交值');
    }

    /** 有效费率只保留 4 位（bcdiv scale=4，截断不进位）—— 0.0001% 直接归零 */
    #[Test]
    public function subPrecisionFeePercentageTruncatesToZero(): void
    {
        $quote = self::quote('100', self::terms(['fee_pct' => '0.0001']));

        $this->assertSame('0.0000', $quote['fee'], "0.0001/100 截断到 4 位即 0.0000");
        $this->assertSame('100.0000', $quote['actual_amount']);
    }

    /** 精度位：最小可提金额 × 100% 费率 = 全额手续费，实收 0 */
    #[Test]
    public function feeAtMinimumAmountKeepsFourDecimalPrecision(): void
    {
        $quote = self::quote('0.0001', self::terms(['fee_pct' => '100']));

        $this->assertSame('0.0001', $quote['fee']);
        $this->assertSame('0.0000', $quote['actual_amount'], '扣完手续费实收 0，不得为负');
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function feeCapCases(): array
    {
        // 金额, 费率%, 封顶, 期望手续费（全部为手算定值，不在测试里重算公式）
        return [
            '未触顶'             => ['1000', '2.5', '50', '25.0000'],
            '恰好触顶'           => ['2000', '2.5', '50', '50.0000'],
            '超出封顶 0.0001'    => ['2000.004', '2.5', '50', '50'],
            '远超封顶'           => ['4000', '2.5', '50', '50'],
            '封顶为 0 不限制'    => ['1000000', '2.5', '0', '25000.0000'],
        ];
    }

    #[Test]
    #[DataProvider('feeCapCases')]
    public function feeIsCappedAtFeeMax(string $amount, string $pct, string $cap, string $expectedFee): void
    {
        $quote = self::quote($amount, self::terms(['fee_pct' => $pct, 'fee_max' => $cap]));

        $this->assertSame($expectedFee, $quote['fee']);
        $this->assertSame(bcsub($amount, $quote['fee'], 4), $quote['actual_amount'], '实收 = 提交值 − 手续费（4 位）');
    }

    /** 极大值不溢出、不丢精度（bcmath 任意精度；float 参与则必然失准） */
    #[Test]
    public function hugeAmountsKeepExactArithmetic(): void
    {
        $quote = self::quote('999999999999999.9999', self::terms(['fee_pct' => '2.5', 'fee_max' => '0']));

        $this->assertSame('24999999999999.9999', $quote['fee'], '含 4 位小数截断');
        $this->assertSame('975000000000000.0000', $quote['actual_amount']);
    }

    /* ---------------- 自动审核阈值（免风控直通的那一段） ---------------- */

    /** @return array<string, array{string, string, bool}> */
    public static function thresholdCases(): array
    {
        return [
            '阈值为 0 即关闭自动审核' => ['0.0001', '0', false],
            '低于阈值'               => ['99.9999', '100', true],
            '恰好等于阈值'           => ['100', '100', false],
            '高于阈值'               => ['100.0001', '100', false],
            '极大阈值'               => ['999999999999999.9999', '999999999999999.9999', false],
        ];
    }

    #[Test]
    #[DataProvider('thresholdCases')]
    public function autoApproveEligibilityIsStrictlyBelowThreshold(string $amount, string $threshold, bool $eligible): void
    {
        $quote = self::quote($amount, self::terms(['auto_approve_threshold' => $threshold]));

        $this->assertSame($eligible, $quote['auto_approve_eligible']);
    }

    /** 阈值资格与手续费互不影响：同一报价里两项各自独立成立 */
    #[Test]
    public function feeAndThresholdAreIndependent(): void
    {
        $quote = self::quote('100', self::terms(['fee_pct' => '2.5', 'auto_approve_threshold' => '200']));

        $this->assertSame('2.5000', $quote['fee']);
        $this->assertTrue($quote['auto_approve_eligible']);
    }

    /* ---------------- 日/月累计限额 ---------------- */

    /** @return array<string, array{string, string, string, bool}> */
    public static function cumulativeCases(): array
    {
        return [
            '未用过额度'         => ['0', '0.0001', '0.0001', false],
            '恰好等于上限'       => ['5', '5', '10', false],
            '超出上限 0.0001'    => ['5', '5.0001', '10', true],
            '已用额度接近上限'   => ['999.9999', '0.0001', '1000', false],
            '累计超出上限 0.0001' => ['1000', '0.0001', '1000', true],
            '已用恰好等于上限'   => ['1000', '0', '1000', false],
            '零上限（不限制）'   => ['1000', '1000', '0', true],
            '负金额抵减已用量'   => ['10', '-5', '10', false],
            '极大值'             => ['999999999999999.9999', '0.0001', '999999999999999.9999', true],
        ];
    }

    #[Test]
    #[DataProvider('cumulativeCases')]
    public function dailyMonthlyLimitComparesUsedPlusAmount(string $used, string $amount, string $limit, bool $exceeded): void
    {
        $this->assertSame($exceeded, self::exceeds($used, $amount, $limit));
    }

    /** 4 位精度截断（bcadd scale=4）：不足 0.0001 的超出量不算突破；恰好到上限也不算突破（> 而非 >=） */
    #[Test]
    public function subPrecisionOvershootDoesNotBreakTheLimit(): void
    {
        $this->assertFalse(self::exceeds('0.0000', '0.00009', '0.0001'), '0.00009 截断为 0.0000');
        $this->assertFalse(self::exceeds('0.0000', '0.0001', '0.0001'), '累计恰好等于上限 = 放行');
    }

    /* ---------------- 层级（KYC → 限额档位） ---------------- */

    #[Test]
    public function onlyApprovedKycSelectsTheVerifiedTier(): void
    {
        $levelOf = static fn (?string $status): string =>
            (new ReflectionMethod(WithdrawController::class, 'withdrawLevel'))->invoke(null, $status);

        $this->assertSame('verified', $levelOf('approved'));
        foreach ([null, 'pending', 'rejected', 'expired', 'PENDING', 'approved ', ''] as $status) {
            $this->assertSame('default', $levelOf($status), var_export($status, true) . ' 不得进 verified 档');
        }
    }
}
