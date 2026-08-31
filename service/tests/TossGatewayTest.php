<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\DepositOrder;
use app\payment\TossGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class TossGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TOSS_SECRET_KEY=test-secret');
    }

    protected function tearDown(): void
    {
        putenv('TOSS_SECRET_KEY');
    }

    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    public function testRedirectConfirmFlow(): void
    {
        $gateway = new FakeTossGateway('DEP1', '100.0000', 'pending');
        $verified = $gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&amount=100'));
        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('PK1', $verified['transaction_id']);
        $this->assertSame('100', $verified['amount']);
        $this->assertSame(1, $gateway->confirmCalls());

        // 金额被篡改：confirm 不被调用，直接拒绝
        $this->assertFalse($gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&amount=999'))['valid']);
        $this->assertSame(1, $gateway->confirmCalls());

        // 缺 paymentKey：拒绝
        $this->assertFalse($gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&amount=100'))['valid']);

        // 幂等：订单已确认后重复回调不再 confirm
        $done = new FakeTossGateway('DEP1', '100.0000', 'confirmed');
        $this->assertTrue($done->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&amount=100'))['valid']);
        $this->assertSame('success', $done->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&amount=100'))['status']);
        $this->assertSame(0, $done->confirmCalls());
    }

    public function testWebhookStatusChanged(): void
    {
        $gateway = new FakeTossGateway('DEP1', '100.0000', 'pending');
        $verified = $gateway->verifyCallback($this->makeRequest('{"eventType":"PAYMENT_STATUS_CHANGED","createdAt":"2022-01-01T00:00:00","data":{"paymentKey":"PK9"}}'));
        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('PK9', $verified['transaction_id']);
        $this->assertSame(1, $gateway->fetchCalls());

        // 金额不符的回查结果：拒绝
        $tampered = new FakeTossGateway('DEP1', '100.0000', 'pending', 'DONE', '50');
        $this->assertFalse($tampered->verifyCallback($this->makeRequest('{"eventType":"PAYMENT_STATUS_CHANGED","data":{"paymentKey":"PK9"}}'))['valid']);

        // ABORTED → failed
        $aborted = new FakeTossGateway('DEP1', '100.0000', 'pending', 'ABORTED');
        $this->assertTrue($aborted->verifyCallback($this->makeRequest('{"eventType":"PAYMENT_STATUS_CHANGED","data":{"paymentKey":"PK9"}}'))['valid']);
        $this->assertSame('failed', $aborted->verifyCallback($this->makeRequest('{"eventType":"PAYMENT_STATUS_CHANGED","data":{"paymentKey":"PK9"}}'))['status']);
    }

    public function testNonPaymentWebhookIgnoredAndFailedPassthrough(): void
    {
        $gateway = new TossGateway();
        // 非 PAYMENT_STATUS_CHANGED 事件：验签无（支付类 webhook 无签名），直接 ignored
        $this->assertTrue($gateway->verifyCallback($this->makeRequest('{"eventType":"DEPOSIT_CALLBACK","data":{}}'))['valid']);
        $this->assertSame('ignored', $gateway->verifyCallback($this->makeRequest('{"eventType":"DEPOSIT_CALLBACK","data":{}}'))['status']);

        // 前端上报失败：直接透传
        $failed = $gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&status=failed'));
        $this->assertTrue($failed['valid']);
        $this->assertSame('failed', $failed['status']);
    }

    public function testMissingSecretFailClosed(): void
    {
        putenv('TOSS_SECRET_KEY');
        try {
            $gateway = new TossGateway();
            $this->assertFalse($gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=toss&order_no=DEP1&paymentKey=PK1&amount=100'))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest('{"eventType":"PAYMENT_STATUS_CHANGED","data":{"paymentKey":"PK1"}}'))['valid']);
            $this->expectException(\RuntimeException::class);
            $gateway->createPayment(new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.0000']), new \common\model\PaymentMethod());
        } finally {
            putenv('TOSS_SECRET_KEY=test-secret');
        }
    }
}

class FakeTossGateway extends TossGateway
{
    private int $confirmCount = 0;
    private int $fetchCount   = 0;

    public function __construct(
        private string $orderNo,
        private string $amount,
        private string $orderStatus,
        private string $paymentStatus = 'DONE',
        private string $paymentAmount = '',
    ) {
        parent::__construct();
    }

    public function confirmCalls(): int
    {
        return $this->confirmCount;
    }

    public function fetchCalls(): int
    {
        return $this->fetchCount;
    }

    protected function findOrder(string $orderNo): ?DepositOrder
    {
        return new DepositOrder([
            'order_no' => $this->orderNo,
            'amount'   => $this->amount,
            'status'   => $this->orderStatus,
        ]);
    }

    protected function confirm(string $paymentKey, string $orderId, string $amount): array
    {
        $this->confirmCount++;
        return [
            'orderId'     => $orderId,
            'paymentKey'  => $paymentKey,
            'totalAmount' => (int) $amount,
            'status'      => $this->paymentStatus,
        ];
    }

    protected function fetchPayment(string $paymentKey): array
    {
        $this->fetchCount++;
        return [
            'orderId'     => $this->orderNo,
            'paymentKey'  => $paymentKey,
            'totalAmount' => (int) ($this->paymentAmount !== '' ? $this->paymentAmount : $this->amount),
            'status'      => $this->paymentStatus,
        ];
    }
}
