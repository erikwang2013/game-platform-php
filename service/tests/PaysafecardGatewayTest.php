<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\PaysafecardGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PaysafecardGatewayTest extends TestCase
{
    private const SECRET = 'test-psc-secret';

    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    private function notification(string $status): string
    {
        return json_encode([
            'object'   => 'PAYMENT',
            'id'       => 'pay_1000000007_Hukab77YIXzKUYMdgPDBQ986ihNUQChu_EUR',
            'amount'   => '50.00',
            'currency' => 'EUR',
            'status'   => $status,
            'customer' => ['id' => 'DEP20260829153000ABC123'],
        ]);
    }

    private function signature(string $body, string $secret = self::SECRET): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public function testAuthorizedParsedAsSuccess(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $body = $this->notification('AUTHORIZED');
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['X-Signature' => $this->signature($body)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('pay_1000000007_Hukab77YIXzKUYMdgPDBQ986ihNUQChu_EUR', $verified['transaction_id']);
            $this->assertSame('50.00', $verified['amount']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testSignatureHeaderNameAccepted(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $body = $this->notification('SUCCESS');
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['Signature' => $this->signature($body)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testFailedMapsToFailed(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $body = $this->notification('FAILED');
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['X-Signature' => $this->signature($body)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testInitiatedIgnored(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $body = $this->notification('INITIATED');
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['X-Signature' => $this->signature($body)]));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testBadSignatureRejected(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $body = $this->notification('AUTHORIZED');
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['X-Signature' => $this->signature($body, 'wrong-secret')]));

            $this->assertFalse($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testMissingSignatureRejected(): void
    {
        putenv('PAYSAFECARD_SECRET=' . self::SECRET);
        try {
            $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($this->notification('AUTHORIZED')));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testMissingSecretRejected(): void
    {
        $body = $this->notification('AUTHORIZED');
        $verified = (new PaysafecardGateway())->verifyCallback($this->makeRequest($body, ['X-Signature' => $this->signature($body)]));

        $this->assertFalse($verified['valid']);
    }
}
