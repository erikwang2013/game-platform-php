<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use app\payment\AstroPayGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use support\Request;

class AstroPayGatewayTest extends TestCase
{
    private const SECRET = 'test-astropay-secret';

    protected function makeRequest(string $body, array $headers = [], string $path = '/api/payment/callback?provider=astropay'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /** 旧版 Direct API 回调签名：md5(order_id.amount.status.secret) */
    private function sign(array $data): string
    {
        return md5($data['order_id'] . $data['amount'] . $data['status'] . self::SECRET);
    }

    private function gateway(): AstroPayGateway
    {
        return new AstroPayGateway(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
    }

    private function withSecret(callable $fn): void
    {
        putenv('ASTROPAY_SECRET=' . self::SECRET);
        try {
            $fn();
        } finally {
            putenv('ASTROPAY_SECRET');
        }
    }

    public function testFormCallbackSuccessParsed(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $data = [
                'order_id'   => 'DEP20260829153000ABC123',
                'amount'     => '100.00',
                'status'     => 'success',
                'payment_id' => 'AP-99',
            ];
            $data['signature'] = $this->sign($data);
            $verified = $gateway->verifyCallback($this->makeRequest(http_build_query($data)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('AP-99', $verified['transaction_id']);
            $this->assertSame('100.00', $verified['amount']);
        });
    }

    public function testJsonCallbackSuccessParsed(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $data = [
                'order_id' => 'DEP1',
                'amount'   => '50.00',
                'status'   => 'success',
            ];
            $data['signature'] = $this->sign($data);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
        });
    }

    public function testPendingIgnored(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $data = ['order_id' => 'DEP1', 'amount' => '10.00', 'status' => 'pending'];
            $data['signature'] = $this->sign($data);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        });
    }

    public function testFailureMapsToFailed(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $data = ['order_id' => 'DEP1', 'amount' => '10.00', 'status' => 'failure'];
            $data['signature'] = $this->sign($data);
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        });
    }

    public function testBadSignatureReturnsInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $data = ['order_id' => 'DEP1', 'amount' => '10.00', 'status' => 'success', 'signature' => md5('wrong')];
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testMissingSignatureReturnsInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withSecret(function () use ($gateway) {
            $verified = $gateway->verifyCallback($this->makeRequest('{"order_id":"DEP1","amount":"10.00","status":"success"}'));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testMissingSecretReturnsInvalid(): void
    {
        $gateway = $this->gateway();
        $data = ['order_id' => 'DEP1', 'amount' => '10.00', 'status' => 'success'];
        $data['signature'] = $this->sign($data);
        $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));
        $this->assertFalse($verified['valid']);
    }

    public function testCreatePaymentMissingEnvThrows(): void
    {
        putenv('ASTROPAY_LOGIN');
        putenv('ASTROPAY_API_KEY');
        $gateway = $this->gateway();
        $order   = new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']);
        $method  = new PaymentMethod();

        $this->expectException(\RuntimeException::class);
        $gateway->createPayment($order, $method);
    }

    public function testCreatePaymentReturnsCheckoutUrl(): void
    {
        $gateway = new AstroPayGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"response":{"url":"https://pay.astropay.com/checkout/AP-1","order_id":"AP-1","status":"success"}}'),
        ]))]));
        putenv('ASTROPAY_LOGIN=test-login');
        putenv('ASTROPAY_API_KEY=test-api-key');
        try {
            $result = $gateway->createPayment(
                new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
                new PaymentMethod()
            );
            $this->assertSame('https://pay.astropay.com/checkout/AP-1', $result['checkout_url']);
            $this->assertSame('AP-1', $result['transaction_id']);
        } finally {
            putenv('ASTROPAY_LOGIN');
            putenv('ASTROPAY_API_KEY');
        }
    }
}
