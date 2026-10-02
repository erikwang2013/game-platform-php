<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\VipLevelController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * VIP benefits 的键白名单与值域
 *
 * 旧实现是 `is_array(json_decode($benefits, true))` —— 它只回答"是不是合法 JSON"，
 * 于是两类错误静默落库：
 *  ① 键名打错：VipService.php:67/79/91 一律 `$benefits[$key] ?? '0'`，打错**不报错**
 *     （按 0 处理）⇒ 运营配了 30% 提现折扣，实际一分没折，且没有任何地方会发现。
 *  ② 值域越界：WithdrawController.php:343 `feePct * (1 - d)` 只夹下界不夹上界
 *     ⇒ d=1.5 或 d=999 时手续费恒为 0（提现免费，静默漏收入）。
 */
class VipBenefitsBoundsTest extends TestCase
{
    /** validateBenefits 是私有的、且不碰 DB —— 只反射这一个纯函数。 */
    private function validate(string $benefits): void
    {
        $method = new ReflectionMethod(VipLevelController::class, 'validateBenefits');

        $method->invoke(new VipLevelController(), $benefits);
    }

    private function rejects(string $benefits): string
    {
        try {
            $this->validate($benefits);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->fail("benefits 应被拒绝但通过了: {$benefits}");
    }

    // ---------------------------------------------------------------- 合法值

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedBenefits(): array
    {
        return [
            '文档样例 0.05/0.30/0.003' => ['{"exchange_discount":0.05,"withdraw_fee_discount":0.30,"rate_bonus":0.003}'],
            '空对象'                   => ['{}'],
            '单键'                     => ['{"exchange_discount":0.1}'],
            '边界 0'                   => ['{"exchange_discount":0}'],
            '边界 1'                   => ['{"withdraw_fee_discount":1}'],
            '字符串数字'               => ['{"rate_bonus":"0.25"}'],
            '整数字面量 0'             => ['{"rate_bonus":0}'],
            'JSON 科学计数 1e-2'       => ['{"rate_bonus":1e-2}'],
            '多位小数'                 => ['{"exchange_discount":0.123456789}'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedBenefits')]
    public function legalBenefitsPass(string $benefits): void
    {
        $this->validate($benefits);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function decimalIsNotReinterpretedAsFloat(): void
    {
        // 0.12345678900000 这类长小数必须在 bcmath 里比较，不能被 float 四舍五入成 0.123456
        $this->validate('{"exchange_discount":0.1000000001}');
        $this->addToAssertionCount(1);

        // 1.0000000001 > 1，超一点点也必须挡住（float 比较会因为精度相近而放过它）
        $this->assertStringContainsString('[0, 1]', $this->rejects('{"exchange_discount":1.0000000001}'));
    }

    // ---------------------------------------------------------------- 值域

    /**
     * @return array<string, array{string}>
     */
    public static function outOfRangeBenefits(): array
    {
        return [
            '提现折扣 1.5'   => ['{"withdraw_fee_discount":1.5}'],
            '提现折扣 999'   => ['{"withdraw_fee_discount":999}'],
            '兑换折扣 >1'    => ['{"exchange_discount":1.01}'],
            '加成率 >1'      => ['{"rate_bonus":1.2}'],
            '负数'           => ['{"exchange_discount":-0.1}'],
            '大负加成'       => ['{"rate_bonus":-2}'],
        ];
    }

    #[Test]
    #[DataProvider('outOfRangeBenefits')]
    public function outOfRangeIsRejected(string $benefits): void
    {
        $this->assertStringContainsString('[0, 1]', $this->rejects($benefits));
    }

    // ---------------------------------------------------------------- 键

    /**
     * @return array<string, array{string}>
     */
    public static function unknownKeys(): array
    {
        return [
            '拼错的键'     => ['{"exchange_discout":0.1}'],
            '驼峰键'       => ['{"exchangeDiscount":0.1}'],
            'create 的键'  => ['{"name":"金牌会员"}'],
            '无关键'       => ['{"unknown":0.1}'],
            '单个合法+单个非法' => ['{"exchange_discount":0.1,"vip_only":true}'],
        ];
    }

    #[Test]
    #[DataProvider('unknownKeys')]
    public function unknownKeyIsRejected(string $benefits): void
    {
        // 打错键必须报错（旧行为是静默按 0 处理），且错误里要列出可用键
        $this->assertStringContainsString('exchange_discount', $this->rejects($benefits));
    }

    // ---------------------------------------------------------------- 形状

    /**
     * @return array<string, array{string}>
     */
    public static function badShapes(): array
    {
        return [
            '非法 JSON'    => ['{not json'],
            '空串'         => [''],
            'JSON 数组'    => ['[0.1, 0.2]'],
            'JSON 标量'    => ['0.5'],
            'null'         => ['null'],
            '嵌套对象当值' => ['{"exchange_discount":{"rate":0.1}}'],
            '布尔当值'     => ['{"exchange_discount":true}'],
            '字符串布尔'   => ['{"exchange_discount":"true"}'],
            '科学计数串'   => ['{"exchange_discount":"1e2"}'],
            '非数字串'     => ['{"exchange_discount":"0.1abc"}'],
        ];
    }

    #[Test]
    #[DataProvider('badShapes')]
    public function badShapeIsRejected(string $benefits): void
    {
        $this->assertNotSame('', $this->rejects($benefits));
    }

    #[Test]
    public function nestedNullValueIsRejected(): void
    {
        // JSON null 解码成 PHP null，match(true) 落到 default 分支 ⇒ 必须报错而非当成 0
        $this->assertNotSame('', $this->rejects('{"exchange_discount":null}'));
    }
}
