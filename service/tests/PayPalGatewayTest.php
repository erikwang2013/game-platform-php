<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use app\payment\PayPalGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PayPalGatewayTest extends TestCase
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

    public function testCaptureCompletedParsedAsSuccess(): void
    {
        $gateway = new PayPalGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP-123","amount":{"currency_code":"USD","value":"100.00"},"purchase_units":[{"reference_id":"DEP20260829153000ABC123"}]}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('CAP-123', $verified['transaction_id']);
        $this->assertSame('100.00', $verified['amount']);
    }

    public function testCaptureDeniedMapsToFailed(): void
    {
        $gateway = new PayPalGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event_type":"PAYMENT.CAPTURE.DENIED","resource":{"id":"CAP-456","amount":{"value":"10.00"},"purchase_units":[{"reference_id":"DEP1"}]}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('failed', $verified['status']);
        $this->assertSame('DEP1', $verified['order_no']);
        $this->assertSame('CAP-456', $verified['transaction_id']);
    }

    public function testPendingEventIgnored(): void
    {
        $gateway = new PayPalGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event_type":"PAYMENT.CAPTURE.PENDING","resource":{"id":"CAP-789"}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testMalformedBodyInvalid(): void
    {
        $gateway = new PayPalGateway();
        $verified = $gateway->verifyCallback($this->makeRequest('not-json'));

        $this->assertFalse($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCreatePaymentFailClosedWithoutCredentials(): void
    {
        putenv('PAYPAL_CLIENT_ID');
        putenv('PAYPAL_CLIENT_SECRET');
        try {
            $order  = new DepositOrder();
            $order->order_no  = 'DEP1';
            $order->amount    = '100.00';
            $order->currency  = 'USD';
            $method = new PaymentMethod();

            $this->expectException(\RuntimeException::class);
            (new PayPalGateway())->createPayment($order, $method);
        } finally {
            putenv('PAYPAL_CLIENT_ID');
            putenv('PAYPAL_CLIENT_SECRET');
        }
    }

    public function testSignatureFailClosedWithoutWebhookId(): void
    {
        putenv('PAYPAL_WEBHOOK_ID');
        try {
            $controller = new \app\api\v1\controller\PaymentController();
            $method = new \ReflectionMethod($controller, 'verifyPayPalSignature');
            $request = $this->makeRequest('{"event_type":"PAYMENT.CAPTURE.COMPLETED"}');
            $this->assertFalse($method->invoke($controller, $request));
        } finally {
            putenv('PAYPAL_WEBHOOK_ID');
        }
    }
}
