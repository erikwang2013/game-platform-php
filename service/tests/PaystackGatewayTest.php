<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\PaystackGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PaystackGatewayTest extends TestCase
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

    private function sign(string $body, string $secret): string
    {
        return hash_hmac('sha512', $body, $secret);
    }

    /** 替换权威回查为固定响应，测试不触发网络 */
    private function gatewayWithVerify(array $verified): PaystackGateway
    {
        return new class ($verified) extends PaystackGateway {
            private array $verified;

            public function __construct(array $verified)
            {
                $this->verified = $verified;
            }

            protected function fetchVerify(string $reference): array
            {
                return $this->verified;
            }
        };
    }

    private function gatewayWithVerifyThrowing(): PaystackGateway
    {
        return new class extends PaystackGateway {
            protected function fetchVerify(string $reference): array
            {
                throw new \RuntimeException('network down');
            }
        };
    }

    public function testChargeSuccessWithVerifyParsedAsSuccess(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP20260829153000ABC123","amount":10000,"currency":"NGN","status":"success"}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            $gateway = $this->gatewayWithVerify(['status' => 'success', 'amount' => 10000, 'reference' => 'DEP20260829153000ABC123']);
            $verified = $gateway->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('DEP20260829153000ABC123', $verified['transaction_id']);
            $this->assertSame('100.0000', $verified['amount']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testVerifyRejectedStatusMapsToFailed(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP1","amount":10000}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            // 回查显示未成功（客户弃单/失败）：即使 webhook 说 charge.success 也按失败处理
            $gateway = $this->gatewayWithVerify(['status' => 'abandoned', 'amount' => 10000]);
            $verified = $gateway->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testVerifyNetworkFailureRejected(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP1","amount":10000}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            $this->assertFalse($this->gatewayWithVerifyThrowing()->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, $secret)]))['valid']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testBadSignatureRejected(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP1","amount":10000}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            $verified = (new PaystackGateway())->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, 'wrong-secret')]));
            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testMissingSignatureRejected(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP1","amount":10000}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            $this->assertFalse((new PaystackGateway())->verifyCallback($this->makeRequest($body))['valid']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testMissingSecretFailsClosed(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"charge.success","data":{"reference":"DEP1","amount":10000}}';

        putenv('PAYSTACK_SECRET_KEY');
        $verified = (new PaystackGateway())->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, $secret)]));
        $this->assertFalse($verified['valid']);
    }

    public function testNonChargeEventIgnored(): void
    {
        $secret = 'sk_test_paystack';
        $body   = '{"event":"transfer.success","data":{"reference":"TRF1"}}';

        putenv("PAYSTACK_SECRET_KEY={$secret}");
        try {
            // 非 charge 事件在回查前短路，真实网关实例也不会触网
            $verified = (new PaystackGateway())->verifyCallback($this->makeRequest($body, ['x-paystack-signature' => $this->sign($body, $secret)]));
            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('PAYSTACK_SECRET_KEY');
        }
    }

    public function testToMinorConvertsKobo(): void
    {
        $gateway = new PaystackGateway();
        $this->assertSame(10000, $gateway->toMinor('100.00'));
        $this->assertSame(100, $gateway->toMinor('1'));
        $this->assertSame(1050, $gateway->toMinor('10.50'));
    }
}
