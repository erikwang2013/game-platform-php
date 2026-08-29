<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\SkrillGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class SkrillGatewayTest extends TestCase
{
    private const MERCHANT_ID = '1392345';
    private const SECRET      = 'secretword';

    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /** md5sig = 大写 MD5(merchant_id + transaction_id + 大写MD5(secret_word) + mb_amount + mb_currency + status) */
    private function md5sig(string $transactionId, string $mbAmount, string $mbCurrency, string $status): string
    {
        return strtoupper(md5(self::MERCHANT_ID . $transactionId . strtoupper(md5(self::SECRET)) . $mbAmount . $mbCurrency . $status));
    }

    private function callbackBody(string $status, string $sig = null, string $transactionId = 'DEP20260829153000ABC123', string $mbAmount = '100.00'): string
    {
        $fields = [
            'merchant_id'        => self::MERCHANT_ID,
            'transaction_id'     => $transactionId,
            'mb_transaction_id'  => '5585262',
            'mb_amount'          => $mbAmount,
            'mb_currency'        => 'EUR',
            'status'             => $status,
            'md5sig'             => $sig ?? $this->md5sig($transactionId, $mbAmount, 'EUR', $status),
        ];
        return http_build_query($fields);
    }

    public function testProcessedParsedAsSuccess(): void
    {
        putenv('SKRILL_SECRET_WORD=' . self::SECRET);
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest($this->callbackBody('2')));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('5585262', $verified['transaction_id']);
            $this->assertSame('100.00', $verified['amount']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testPendingIgnored(): void
    {
        putenv('SKRILL_SECRET_WORD=' . self::SECRET);
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest($this->callbackBody('0')));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testFailedMapsToFailed(): void
    {
        putenv('SKRILL_SECRET_WORD=' . self::SECRET);
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest($this->callbackBody('-2')));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testBadSignatureRejected(): void
    {
        putenv('SKRILL_SECRET_WORD=' . self::SECRET);
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest($this->callbackBody('2', str_repeat('0', 32))));

            $this->assertFalse($verified['valid']);
            $this->assertSame('failed', $verified['status']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testMissingSignatureRejected(): void
    {
        putenv('SKRILL_SECRET_WORD=' . self::SECRET);
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $fields = [
                'merchant_id'       => self::MERCHANT_ID,
                'transaction_id'    => 'DEP20260829153000ABC123',
                'mb_transaction_id' => '5585262',
                'mb_amount'         => '100.00',
                'mb_currency'       => 'EUR',
                'status'            => '2',
            ];
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest(http_build_query($fields)));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testMissingSecretWordRejected(): void
    {
        putenv('SKRILL_MERCHANT_ID=' . self::MERCHANT_ID);
        try {
            $verified = (new SkrillGateway())->verifyCallback($this->makeRequest($this->callbackBody('2')));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('SKRILL_MERCHANT_ID');
        }
    }
}
