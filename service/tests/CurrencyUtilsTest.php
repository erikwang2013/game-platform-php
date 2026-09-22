<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\AdyenGateway;
use app\payment\CurrencyUtils;
use app\payment\GatewayCapabilities;
use app\payment\GatewayFactory;
use app\payment\GrabPayGateway;
use PHPUnit\Framework\TestCase;

class CurrencyUtilsTest extends TestCase
{
    public function testToMinorNormalCurrency(): void
    {
        $this->assertSame('10000', CurrencyUtils::toMinor('100.00', 'USD'));
        $this->assertSame('50', CurrencyUtils::toMinor('0.50', 'EUR'));
    }

    public function testToMinorZeroDecimalCurrency(): void
    {
        $this->assertSame('100', CurrencyUtils::toMinor('100', 'JPY'));
        $this->assertSame('100', CurrencyUtils::toMinor('100', 'KRW'));
        $this->assertTrue(CurrencyUtils::isZeroDecimal('vnd'));
    }

    public function testFromMinor(): void
    {
        $this->assertSame('100.0000', CurrencyUtils::fromMinor('10000', 'USD'));
        $this->assertSame('100', CurrencyUtils::fromMinor('100', 'JPY'));
    }

    public function testPrecisionOkZeroDecimalCurrency(): void
    {
        $this->assertTrue(CurrencyUtils::precisionOk('1000', 'JPY'), '零小数币种整数金额必须合法');
        $this->assertTrue(CurrencyUtils::precisionOk('1000', 'jpy'));
        $this->assertTrue(CurrencyUtils::precisionOk('1000', 'KRW'));
        $this->assertFalse(CurrencyUtils::precisionOk('1000.5', 'JPY'), '零小数币种不接受小数点');
        $this->assertFalse(CurrencyUtils::precisionOk('1000.0', 'JPY'));
    }

    public function testPrecisionOkNormalCurrency(): void
    {
        $this->assertTrue(CurrencyUtils::precisionOk('100', 'USD'));
        $this->assertTrue(CurrencyUtils::precisionOk('0.50', 'USD'));
        $this->assertTrue(CurrencyUtils::precisionOk('100.5', 'USD'));
        $this->assertFalse(CurrencyUtils::precisionOk('100.555', 'USD'), '超过 2 位小数必须拒绝');
        $this->assertFalse(CurrencyUtils::precisionOk('1e3', 'USD'));
        $this->assertFalse(CurrencyUtils::precisionOk('abc', 'USD'));
    }

    public function testGatewayFactoryResolvesNewGateways(): void
    {
        $this->assertInstanceOf(AdyenGateway::class, GatewayFactory::resolve('adyen'));
        $this->assertInstanceOf(GrabPayGateway::class, GatewayFactory::resolve('grabpay'));

        $this->assertTrue(GatewayCapabilities::supportsRefund(GatewayFactory::resolve('adyen')));
        $this->assertFalse(GatewayCapabilities::supportsRefund(GatewayFactory::resolve('grabpay')));
        $this->assertTrue(GatewayCapabilities::supportsQuery(GatewayFactory::resolve('adyen')));

        $this->expectException(\InvalidArgumentException::class);
        GatewayFactory::resolve('no-such-provider');
    }
}
