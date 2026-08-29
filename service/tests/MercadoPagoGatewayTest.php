<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use app\payment\MercadoPagoGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use support\Request;

class MercadoPagoGatewayTest extends TestCase
{
    private const SECRET  = 'test-mp-webhook-secret';
    private const DATA_ID = '12345';

    protected function makeRequest(string $body, array $headers = [], string $path = '/api/payment/callback?provider=mercadopago&data_id=12345'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /** 生成官方 SDK 同款 manifest 的合法签名头：id:{data_id};request-id:{x-request-id};ts:{ts}; */
    private function signedHeaders(?int $ts = null): array
    {
        $ts        = $ts ?? time();
        $requestId = 'req-1';
        $v1        = hash_hmac('sha256', "id:" . self::DATA_ID . ";request-id:{$requestId};ts:{$ts};", self::SECRET);
        return ['X-Signature' => "ts={$ts},v1={$v1}", 'X-Request-ID' => $requestId];
    }

    private function gatewayWithMock(array $responses): MercadoPagoGateway
    {
        return new MercadoPagoGateway(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    public function testApprovedPaymentParsedAsSuccess(): void
    {
        $gateway = $this->gatewayWithMock([
            new Response(200, [], '{"access_token":"tok-test"}'),
            new Response(200, [], '{"id":"12345","external_reference":"DEP20260829153000ABC123","transaction_amount":100.0,"status":"approved"}'),
        ]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        putenv('MERCADOPAGO_CLIENT_ID=test-client');
        putenv('MERCADOPAGO_CLIENT_SECRET=test-secret');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}', $this->signedHeaders()));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('12345', $verified['transaction_id']);
            $this->assertSame('100', $verified['amount']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
            putenv('MERCADOPAGO_CLIENT_ID');
            putenv('MERCADOPAGO_CLIENT_SECRET');
        }
    }

    public function testRejectedPaymentMapsToFailed(): void
    {
        $gateway = $this->gatewayWithMock([
            new Response(200, [], '{"access_token":"tok-test"}'),
            new Response(200, [], '{"id":"12345","external_reference":"DEP1","status":"rejected"}'),
        ]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        putenv('MERCADOPAGO_CLIENT_ID=test-client');
        putenv('MERCADOPAGO_CLIENT_SECRET=test-secret');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}', $this->signedHeaders()));
            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
            putenv('MERCADOPAGO_CLIENT_ID');
            putenv('MERCADOPAGO_CLIENT_SECRET');
        }
    }

    public function testInProcessPaymentIgnored(): void
    {
        $gateway = $this->gatewayWithMock([
            new Response(200, [], '{"access_token":"tok-test"}'),
            new Response(200, [], '{"id":"12345","external_reference":"DEP1","status":"in_process"}'),
        ]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        putenv('MERCADOPAGO_CLIENT_ID=test-client');
        putenv('MERCADOPAGO_CLIENT_SECRET=test-secret');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}', $this->signedHeaders()));
            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
            putenv('MERCADOPAGO_CLIENT_ID');
            putenv('MERCADOPAGO_CLIENT_SECRET');
        }
    }

    public function testSignatureMismatchReturnsInvalid(): void
    {
        // mock 为空：验签失败前不允许发出任何 HTTP 请求
        $gateway = $this->gatewayWithMock([]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        try {
            $headers       = $this->signedHeaders();
            $headers['X-Signature'] = 'ts=' . time() . ',v1=' . str_repeat('0', 64);
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}', $headers));
            $this->assertFalse($verified['valid']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
        }
    }

    public function testStaleTimestampRejected(): void
    {
        $gateway = $this->gatewayWithMock([]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                '{"type":"payment","data":{"id":"12345"}}',
                $this->signedHeaders(time() - 3600)
            ));
            $this->assertFalse($verified['valid']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
        }
    }

    public function testMissingSignatureHeaderReturnsInvalid(): void
    {
        $gateway = $this->gatewayWithMock([]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}'));
            $this->assertFalse($verified['valid']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
        }
    }

    public function testMissingWebhookSecretReturnsInvalid(): void
    {
        $gateway = $this->gatewayWithMock([]);
        $verified = $gateway->verifyCallback($this->makeRequest('{"type":"payment","data":{"id":"12345"}}', $this->signedHeaders()));
        $this->assertFalse($verified['valid']);
    }

    public function testNonPaymentEventIgnoredWithoutHttp(): void
    {
        // 非 payment 事件在回查前即返回 ignored，mock 为空可证明没有发 HTTP
        $gateway = $this->gatewayWithMock([]);
        putenv('MERCADOPAGO_WEBHOOK_SECRET=' . self::SECRET);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                '{"type":"orders","data":{"id":"12345"}}',
                $this->signedHeaders()
            ));
            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
        }
    }

    public function testCreatePaymentMissingEnvThrows(): void
    {
        putenv('MERCADOPAGO_CLIENT_ID');
        putenv('MERCADOPAGO_CLIENT_SECRET');
        $gateway = $this->gatewayWithMock([]);
        $order   = new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']);
        $method  = new PaymentMethod();

        $this->expectException(\RuntimeException::class);
        $gateway->createPayment($order, $method);
    }

    public function testCreatePaymentReturnsCheckoutUrl(): void
    {
        $gateway = $this->gatewayWithMock([
            new Response(200, [], '{"access_token":"tok-test"}'),
            new Response(200, [], '{"id":"pref-123","init_point":"https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-123"}'),
        ]);
        putenv('MERCADOPAGO_CLIENT_ID=test-client');
        putenv('MERCADOPAGO_CLIENT_SECRET=test-secret');
        try {
            $result = $gateway->createPayment(
                new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
                new PaymentMethod()
            );
            $this->assertSame('https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-123', $result['checkout_url']);
            $this->assertSame('pref-123', $result['transaction_id']);
        } finally {
            putenv('MERCADOPAGO_CLIENT_ID');
            putenv('MERCADOPAGO_CLIENT_SECRET');
        }
    }
}
