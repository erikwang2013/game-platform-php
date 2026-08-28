<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\CountryConfig;
use app\payment\CoinbaseCommerceGateway;
use app\payment\GatewayFactory;
use app\payment\NowPaymentsGateway;
use app\payment\StripeGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PaymentGatewayTest extends TestCase
{
    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    public function testNowPaymentsFinishedParsedAsSuccess(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"finished","order_id":"DEP20260829153000ABC123","payment_id":"12345","price_amount":"100.0000"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('12345', $verified['transaction_id']);
        $this->assertSame('100.0000', $verified['amount']);
    }

    public function testNowPaymentsWaitingIgnored(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"waiting","order_id":"DEP1","payment_id":"1"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testNowPaymentsFailedMapsToFailed(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"failed","order_id":"DEP1","payment_id":"1"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCoinbaseConfirmedParsedAsSuccess(): void
    {
        $gateway = new CoinbaseCommerceGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event":{"type":"charge:confirmed","data":{"id":"cb-1","metadata":{"order_no":"DEP20260829153000ABC123"},"pricing":{"local":{"amount":"50.00"}}}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('cb-1', $verified['transaction_id']);
        $this->assertSame('50.00', $verified['amount']);
    }

    public function testCoinbaseCreatedIgnored(): void
    {
        $gateway = new CoinbaseCommerceGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event":{"type":"charge:created"},"data":{"id":"cb-1","metadata":{"order_no":"DEP1"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testStripeCheckoutCompletedParsedAsSuccess(): void
    {
        $gateway = new StripeGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"type":"checkout.session.completed","data":{"object":{"id":"cs_test_1","metadata":{"order_no":"DEP20260829153000ABC123"},"amount_total":10000,"currency":"usd"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('cs_test_1', $verified['transaction_id']);
        $this->assertSame('100.0000', $verified['amount']);
    }

    public function testStripeOtherEventIgnored(): void
    {
        $gateway = new StripeGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"type":"checkout.session.expired","data":{"object":{"id":"cs_test_1"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testToMinorZeroDecimalCurrencies(): void
    {
        $gateway = new StripeGateway();

        $this->assertSame('10000', $gateway->toMinor('100.00', 'USD'));
        $this->assertSame('100', $gateway->toMinor('100', 'JPY'));
        $this->assertSame('100', $gateway->toMinor('100.00', 'KRW'));
    }

    public function testNowPaymentsSignatureVerification(): void
    {
        $secret = 'test-ipn-secret';
        $body   = '{"payment_status":"finished","order_id":"DEP1","payment_id":"1"}';
        $sig    = hash_hmac('sha512', $body, $secret);

        putenv("NOWPAYMENTS_IPN_SECRET={$secret}");
        try {
            $this->assertTrue($this->verifyNowPaymentsSignature($body, $sig));
            $this->assertFalse($this->verifyNowPaymentsSignature($body, hash_hmac('sha512', $body, 'wrong-secret')));
            $this->assertFalse($this->verifyNowPaymentsSignature($body, ''));
        } finally {
            putenv('NOWPAYMENTS_IPN_SECRET');
        }
    }

    public function testCoinbaseSignatureVerification(): void
    {
        $secret = base64_encode('test-cc-secret');
        $body   = '{"event":{"type":"charge:confirmed"}}';
        $sig    = base64_encode(hash_hmac('sha256', $body, base64_decode($secret)));

        putenv("COINBASE_COMMERCE_WEBHOOK_SECRET={$secret}");
        try {
            $this->assertTrue($this->verifyCoinbaseSignature($body, $sig));
            $this->assertFalse($this->verifyCoinbaseSignature($body, base64_encode(hash_hmac('sha256', $body, 'wrong'))));
            $this->assertFalse($this->verifyCoinbaseSignature($body, ''));
        } finally {
            putenv('COINBASE_COMMERCE_WEBHOOK_SECRET');
        }
    }

    public function testCountryConfigFromLangMapping(): void
    {
        $this->assertSame('CN', CountryConfig::fromLang('zh-CN'));
        $this->assertSame('JP', CountryConfig::fromLang('ja'));
        $this->assertSame('KR', CountryConfig::fromLang('ko-KR'));
        $this->assertSame('BR', CountryConfig::fromLang('pt-BR'));
        $this->assertSame('IN', CountryConfig::fromLang('hi-IN'));
        $this->assertSame('DE', CountryConfig::fromLang('de-DE'));
        $this->assertSame('US', CountryConfig::fromLang('en-US'));
        $this->assertSame('', CountryConfig::fromLang('xx-YY'));
        $this->assertSame('', CountryConfig::fromLang(''));
    }

    public function testGatewayFactoryResolvesProviders(): void
    {
        $this->assertInstanceOf(NowPaymentsGateway::class, GatewayFactory::resolve('nowpayments'));
        $this->assertInstanceOf(CoinbaseCommerceGateway::class, GatewayFactory::resolve('coinbase'));
        $this->assertInstanceOf(StripeGateway::class, GatewayFactory::resolve('stripe'));

        $this->expectException(\InvalidArgumentException::class);
        GatewayFactory::resolve('bitcoin');
    }

    private function verifyNowPaymentsSignature(string $body, string $signature): bool
    {
        $controller = new \app\api\v1\controller\PaymentController();
        $method = new \ReflectionMethod($controller, 'verifyNowPaymentsSignature');
        $request = $this->makeRequest($body, ['X-NowPayments-Sig' => $signature]);
        return $method->invoke($controller, $request);
    }

    private function verifyCoinbaseSignature(string $body, string $signature): bool
    {
        $controller = new \app\api\v1\controller\PaymentController();
        $method = new \ReflectionMethod($controller, 'verifyCoinbaseSignature');
        $request = $this->makeRequest($body, ['X-CC-Webhook-Signature' => $signature]);
        return $method->invoke($controller, $request);
    }
}
