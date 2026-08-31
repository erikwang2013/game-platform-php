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
