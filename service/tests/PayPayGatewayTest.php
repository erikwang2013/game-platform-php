<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\PayPayGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PayPayGatewayTest extends TestCase
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

    /** 构造 PayPay 签名头：hash=hmac(body.ts),ts=unix */
    private function signature(string $body, string $secret, ?int $ts = null): string
    {
        $ts = $ts ?? time();
        return 'hash=' . hash_hmac('sha256', $body . (string) $ts, $secret) . ',ts=' . (string) $ts;
    }

    public function testCapturedParsedAsSuccess(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP20260829153000ABC123","paymentId":"pay-1","amount":{"amount":100,"currency":"JPY"}}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['PayPay-Signature' => $this->signature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('pay-1', $verified['transaction_id']);
            $this->assertSame('100', $verified['amount']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testNestedPayloadShapeParsed(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"payment":{"merchantPaymentId":"DEP20260829153000ABC123","paymentId":"pay-2","amount":{"amount":5000,"currency":"JPY"}}}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['X-PayPay-Signature' => $this->signature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('pay-2', $verified['transaction_id']);
            $this->assertSame('5000', $verified['amount']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testBadSignatureRejected(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP1"}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['PayPay-Signature' => $this->signature($body, 'wrong-secret')]));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testMissingSignatureRejected(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP1"}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testMissingSecretRejected(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP1"}}';

        putenv('PAYPAY_SIGNING_KEY');
        $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['PayPay-Signature' => $this->signature($body, $secret)]));

        $this->assertFalse($verified['valid']);
    }

    public function testStaleTimestampRejected(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP1"}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest(
                $body,
                ['PayPay-Signature' => $this->signature($body, $secret, time() - 3600)]
            ));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testFailedEventMapsToFailed(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_FAILED","data":{"merchantPaymentId":"DEP1","paymentId":"pay-3"}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['PayPay-Signature' => $this->signature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testCreatedEventIgnored(): void
    {
        $secret = 'paypay-signing-key';
        $body   = '{"event":"PAYMENT_CREATED","data":{"merchantPaymentId":"DEP1","paymentId":"pay-4"}}';

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $verified = (new PayPayGateway())->verifyCallback($this->makeRequest($body, ['PayPay-Signature' => $this->signature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }
}
