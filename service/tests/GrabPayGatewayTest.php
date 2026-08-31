<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use app\payment\GrabPayGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use support\Request;

class GrabPayGatewayTest extends TestCase
{
    private const SECRET = 'test-grabpay-secret';
    private const MERCHANT_ID = 'GRAB-TEST-001';

    protected function makeRequest(string $body, array $headers = [], string $path = '/api/payment/callback?provider=grabpay'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /** 与网关一致的签名算法：key 字典序拼接 "key:value"（数组取紧凑 JSON），HMAC-SHA256 hex */
    private function sign(array $payload): string
    {
        ksort($payload);
        $str = '';
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $str .= $key . ':' . $value;
        }
        return hash_hmac('sha256', $str, self::SECRET);
    }

    private function withEnv(array $env, callable $fn): void
    {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
        try {
            $fn();
        } finally {
            foreach ($env as $key => $_) {
                putenv($key);
            }
        }
    }

    private function gateway(): GrabPayGateway
    {
        return new GrabPayGateway(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
    }

    private function callbackPayload(array $overrides = []): array
    {
        return array_merge([
            'partnerTxnID' => 'DEP1',
            'referenceID'  => 'DEP1',
            'amount'       => '100.00',
            'currency'     => 'USD',
            'country'      => 'SG',
            'txnStatus'    => 'SUCCESS',
        ], $overrides);
    }

    public function testCreatePaymentReturnsCheckoutUrl(): void
    {
        $gateway = new GrabPayGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"referenceID":"DEP1","redirectUrl":"https://grabpay.test/pay/DEP1"}'),
        ]))]));
        $this->withEnv(['GRABPAY_MERCHANT_ID' => self::MERCHANT_ID, 'GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            // config 为加密字段不能走构造器注入；未配置时网关默认 country=SG，测试仅校验 URL 与单号
            $method = new PaymentMethod();
            $result = $gateway->createPayment(
                new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
                $method
            );
            $this->assertSame('https://grabpay.test/pay/DEP1', $result['checkout_url']);
            $this->assertSame('DEP1', $result['transaction_id']);
        });
    }

    public function testCreatePaymentMissingEnvThrows(): void
    {
        putenv('GRABPAY_MERCHANT_ID');
        putenv('GRABPAY_SECRET');
        $this->expectException(\RuntimeException::class);
        $this->gateway()->createPayment(
            new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
            new PaymentMethod()
        );
    }

    public function testCallbackSuccess(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            $data     = $this->callbackPayload();
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data), [
                'x-signature' => $this->sign($data),
            ]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
            $this->assertSame('DEP1', $verified['transaction_id']);
            $this->assertSame('100.00', $verified['amount']);
        });
    }

    public function testCallbackBadSignatureInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            $data = $this->callbackPayload(['amount' => '1.00']); // 金额被篡改，签名是原金额的
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data), [
                'x-signature' => $this->sign($this->callbackPayload()),
            ]));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testCallbackPendingIgnored(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            $data     = $this->callbackPayload(['txnStatus' => 'PENDING']);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data), [
                'x-signature' => $this->sign($data),
            ]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        });
    }

    public function testCallbackFailureMapsToFailed(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            $data     = $this->callbackPayload(['txnStatus' => 'FAILED']);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data), [
                'x-signature' => $this->sign($data),
            ]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        });
    }

    public function testCallbackSuccessMissingAmountInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['GRABPAY_SECRET' => self::SECRET], function () use ($gateway) {
            $data     = $this->callbackPayload(['amount' => '']);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data), [
                'x-signature' => $this->sign($data),
            ]));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testCallbackMissingSecretInvalid(): void
    {
        putenv('GRABPAY_SECRET');
        $data     = $this->callbackPayload();
        $verified = $this->gateway()->verifyCallback($this->makeRequest(json_encode($data), [
            'x-signature' => $this->sign($data),
        ]));
        $this->assertFalse($verified['valid']);
    }
}
