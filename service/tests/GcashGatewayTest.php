<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\GcashGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class GcashGatewayTest extends TestCase
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

    /** 构造复合格式签名头：t=...,te=...,li=...，签名串 = t . '.' . body */
    private function compositeSignature(string $body, string $secret, ?int $ts = null): string
    {
        $ts = $ts ?? time();
        return 't=' . $ts . ',te=,li=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
    }

    /** 构造仅 te（测试模式）的复合签名头 */
    private function testModeSignature(string $body, string $secret, ?int $ts = null): string
    {
        $ts = $ts ?? time();
        return 't=' . $ts . ',te=' . hash_hmac('sha256', $ts . '.' . $body, $secret) . ',li=';
    }

    public function testPaymentIntentSucceededParsedAsSuccess(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded","data":{"id":"pi_1","type":"payment_intent","attributes":{"amount":10000,"currency":"PHP","status":"succeeded","metadata":{"order_no":"DEP20260829153000ABC123"}}}}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->compositeSignature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('pi_1', $verified['transaction_id']);
            $this->assertSame('100.0000', $verified['amount']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testPaymentPaidWithTestModeSignatureParsedAsSuccess(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_2","type":"event","attributes":{"type":"payment.paid","data":{"id":"pay_1","type":"payment","attributes":{"amount":5000,"currency":"PHP","metadata":{"order_no":"DEP20260829153000ABC123"}}}}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->testModeSignature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('pay_1', $verified['transaction_id']);
            $this->assertSame('50.0000', $verified['amount']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testBadSignatureRejected(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded"}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->compositeSignature($body, 'wrong-secret')]));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testMissingSignatureRejected(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded"}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testMissingSecretRejected(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded"}}}';

        putenv('PAYMONGO_WEBHOOK_SECRET');
        $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->compositeSignature($body, $secret)]));

        $this->assertFalse($verified['valid']);
    }

    public function testStaleTimestampRejected(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded"}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest(
                $body,
                ['Paymongo-Signature' => $this->compositeSignature($body, $secret, time() - 3600)]
            ));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testPlainHexSignatureAccepted(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"payment_intent.succeeded"}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest(
                $body,
                ['Paymongo-Signature' => hash_hmac('sha256', $body, $secret)]
            ));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testPaymentFailedMapsToFailed(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_3","type":"event","attributes":{"type":"payment.failed","data":{"id":"pay_2","type":"payment","attributes":{"amount":10000,"currency":"PHP","metadata":{"order_no":"DEP1"}}}}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->compositeSignature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testOtherEventIgnored(): void
    {
        $secret = 'paymongo-webhook-secret';
        $body   = '{"data":{"id":"evt_4","type":"event","attributes":{"type":"refund.succeeded"}}}';

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $verified = (new GcashGateway())->verifyCallback($this->makeRequest($body, ['Paymongo-Signature' => $this->compositeSignature($body, $secret)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }
}
