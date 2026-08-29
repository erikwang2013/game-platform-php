<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\NetellerGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class NetellerGatewayTest extends TestCase
{
    private const SECRET = '23haJ20opHJ2ks38aGEnw';

    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    private function event(string $eventType, string $key, string $ref = 'DEP20260829153000ABC123', string $id = 'ebecd052-757f-4991-9f19-469d21e6c065'): string
    {
        return json_encode([
            'mode'           => 'live',
            'id'             => $id,
            'eventDate'      => '2026-08-29T10:00:00Z',
            'eventType'      => $eventType,
            'attemptNumber'  => 1,
            'key'            => $key,
            'transaction_ref' => $ref,
            'amount'         => '50.00',
        ]);
    }

    public function testPaymentSucceededParsedAsSuccess(): void
    {
        putenv('NETELLER_SECRET=' . self::SECRET);
        try {
            $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_succeeded', self::SECRET)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('ebecd052-757f-4991-9f19-469d21e6c065', $verified['transaction_id']);
            $this->assertSame('50.00', $verified['amount']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testPaymentDeclinedMapsToFailed(): void
    {
        putenv('NETELLER_SECRET=' . self::SECRET);
        try {
            $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_declined', self::SECRET)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testPaymentPendingIgnored(): void
    {
        putenv('NETELLER_SECRET=' . self::SECRET);
        try {
            $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_pending', self::SECRET)));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testBadKeyRejected(): void
    {
        putenv('NETELLER_SECRET=' . self::SECRET);
        try {
            $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_succeeded', 'wrong-key')));

            $this->assertFalse($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testMissingKeyRejected(): void
    {
        putenv('NETELLER_SECRET=' . self::SECRET);
        try {
            $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_succeeded', '')));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testMissingSecretRejected(): void
    {
        $verified = (new NetellerGateway())->verifyCallback($this->makeRequest($this->event('payment_succeeded', self::SECRET)));

        $this->assertFalse($verified['valid']);
    }
}
