<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ExchangeController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 兑换两侧发生额（ExchangeController::exchangeLegs，quote 与 buy/sell 共用）。
 *
 * 回归背景：sell 分支曾把折算后的平台币数（输入 ÷ 汇率）当成要扣的游戏币数。
 * 用户卖出 950 游戏币时只扣 9.5、却按 9.025 的平台币净额入账 —— 汇率形同 1:1，
 * 每次卖出都在铸币。同一处取值还流向三个下游，错一次错三处：
 *   - 响应 platform_amount/game_amount（客户端展示 sell 成交明细）
 *   - game_exchange_record 的列（install.sql 注释：platform_amount=平台币数量、game_amount=游戏币数量）
 *   - 后台统计 AnalyticsController「销毁游戏币」= sum(game_amount) where direction=out
 * 故必须钉住「out 扣的就是提交值」，而不是断言"响应码 200"这种静默无效的检查。
 *
 * 纯计算、不触库：exchangeLegs 只做 bcmath，无需数据库即可断言。
 */
class ExchangeControllerLegsTest extends TestCase
{
    /** @return array<string, string> */
    private static function legs(string $direction, string $amount, string $rate = '100', string $spread = '5'): array
    {
        return (new ReflectionMethod(ExchangeController::class, 'exchangeLegs'))
            ->invoke(null, $direction, $amount, $rate, $spread);
    }

    /**
     * 核心回归：out 扣减的游戏币 = 提交值原样，而不是 提交值 ÷ 汇率。
     * 旧实现返回 '9.50000000'（折算后的平台币），本断言即失败。
     */
    #[Test]
    public function sellDeductsSubmittedGameAmountNotThePlatformEquivalent(): void
    {
        $legs = self::legs('out', '950');

        $this->assertSame('950', $legs['game_amount'], '卖出的游戏币必须原值扣减');
        $this->assertSame('9.50000000', $legs['platform_gross'], '折算平台币（扣点差前）');
        $this->assertSame('0.47500000', $legs['spread_fee'], '点差按平台币计');
        $this->assertSame('9.02500000', $legs['platform_amount'], '到账平台币为扣点差净值');
    }

    /**
     * 汇率越高，旧实现的少扣越严重（旧值 = 提交值 ÷ 汇率）。逐档钉住提交值，
     * 只要有人把 game_amount 改回任何依赖汇率的表达式，本用例即失败。
     *
     * @param non-empty-string $amount
     */
    #[Test]
    #[DataProvider('sellAmounts')]
    public function sellGameAmountIsRateIndependent(string $amount, string $rate): void
    {
        $this->assertSame($amount, self::legs('out', $amount, $rate)['game_amount']);
    }

    /** @return array<string, array{string, string}> */
    public static function sellAmounts(): array
    {
        return [
            // 汇率 1:1 时新旧实现同值 —— 正因如此，用默认汇率测不出该缺陷
            '平价'     => ['50', '1'],
            '百倍汇率' => ['50', '100'],
            '小数金额' => ['12.3456', '7.5'],
        ];
    }

    /** 到账净额必须 ≤ 折算额，且点差为 0 时两者相等。 */
    #[Test]
    public function sellFeeIsDeductedFromThePlatformSide(): void
    {
        $legs = self::legs('out', '950', '100', '0');

        $this->assertSame('0.00000000', $legs['spread_fee']);
        $this->assertSame($legs['platform_gross'], $legs['platform_amount']);

        $charged = self::legs('out', '950', '100', '5');
        $this->assertSame(-1, bccomp($charged['platform_amount'], $charged['platform_gross'], 8));
    }

    /** buy 方向不受本回归影响，一并钉住以防共用实现时改坏。 */
    #[Test]
    public function buySpendsPlatformAndReceivesNetGameAmount(): void
    {
        $legs = self::legs('in', '10');

        $this->assertSame('10', $legs['platform_amount'], '支出平台币 = 提交值');
        $this->assertSame('1000.00000000', $legs['game_gross'], '10 × 100');
        $this->assertSame('50.00000000', $legs['spread_fee']);
        $this->assertSame('950.00000000', $legs['game_amount'], '到账游戏币为扣点差净值');
    }
}
