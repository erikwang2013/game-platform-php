<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\ReconciliationService;
use common\BcMath;
use PHPUnit\Framework\TestCase;

/**
 * 回归：amountGap() 曾调用不存在的 bcabs()（PHP 无此函数，全仓/vendor 零定义）。
 *
 * 该方法是纯函数且被 reconcile() 主匹配循环逐条调用（ReconciliationService.php:238），
 * 因此只要批次里有一条明细匹配上本地订单就会抛 Error，被 runBatch()/importCsv() 的
 * catch (\Throwable) 兜住并 markFailed() —— 差异永不落库、匹配明细也不落库。
 *
 * 这里用反射直调私有纯函数，不连库，可在无 MySQL 环境完整跑通。
 */
class ReconciliationAmountGapTest extends TestCase
{
    /** 反射调用私有静态 amountGap（纯字符串运算，无 IO） */
    private static function amountGap(string $gatewayAmount, string $localAmount): string
    {
        $method = new \ReflectionMethod(ReconciliationService::class, 'amountGap');
        $result = $method->invoke(null, $gatewayAmount, $localAmount);
        self::assertIsString($result);

        return $result;
    }

    public function testGapReturnsAbsoluteDifference(): void
    {
        $this->assertSame('2.5000', self::amountGap('10.0000', '7.5000'));
        // 网关金额低于本地时必须同样返回正值——差异描述里的符号由调用方单独判定
        $this->assertSame('2.5000', self::amountGap('7.5000', '10.0000'));
    }

    public function testGapIsZeroForEqualAmounts(): void
    {
        $this->assertSame('0.0000', self::amountGap('10.0000', '10.0000'));
    }

    public function testGapNeverCarriesMinusSign(): void
    {
        foreach ([['1.0000', '9.0000'], ['0', '3.1400'], ['-5.0000', '5.0000']] as [$gw, $local]) {
            $this->assertStringStartsNotWith('-', self::amountGap($gw, $local), "网关 {$gw} / 本地 {$local}");
        }
    }

    public function testToleranceComparesAgainstPositiveGap(): void
    {
        // 调用方口径（ReconciliationService.php:239）：bccomp(gap, '0.01', 4) > 0 才记金额不一致
        $this->assertFalse(bccomp(self::amountGap('10.0050', '10.0000'), '0.01', 4) > 0, '容差内不记差异');
        $this->assertTrue(bccomp(self::amountGap('10.0200', '10.0000'), '0.01', 4) > 0, '超容差记差异');
        // 反向金额（本地 > 网关）超容差同样必须被记——符号曾由不存在的 bcabs 决定，负数会被漏判
        $this->assertTrue(bccomp(self::amountGap('10.0000', '10.0200'), '0.01', 4) > 0, '反向超容差记差异');
    }

    public function testBcMathAbsStripsSign(): void
    {
        $this->assertSame('1.5000', BcMath::abs('-1.5000'));
        $this->assertSame('1.5000', BcMath::abs('1.5000'));
        // 负数零归一：bcsub 可能给出 '-0.0000'，绝对值应抹掉符号
        $this->assertSame('0.0000', BcMath::abs('-0.0000'));
        $this->assertSame('0', BcMath::abs('0'));
    }
}
