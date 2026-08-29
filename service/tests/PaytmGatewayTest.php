<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\payment\GatewayFactory;
use app\payment\PaytmGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PaytmGatewayTest extends TestCase
{
    private const TEST_KEY = 'test-merchant-key';

    protected function makeRequest(string $body, array $headers = [], string $method = 'POST', string $path = '/'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /** 构造 Paytm 回调报文：NVP 参数 + 用指定密钥计算 CHECKSUMHASH */
    private function callbackBody(array $params, string $key): string
    {
        $params['CHECKSUMHASH'] = PaytmGateway::signNvp($params, $key);
        return http_build_query($params);
    }

    public function testCallbackSuccessParsedAsSuccess(): void
    {
        $gateway = new PaytmGateway();
        $body = $this->callbackBody([
            'MID'      => 'TESTMID123',
            'ORDERID'  => 'DEP20260829153000ABC123',
            'TXNID'    => '20260829111212800110168559404001025',
            'TXNAMOUNT'=> '100.00',
            'STATUS'   => 'TXN_SUCCESS',
            'CURRENCY' => 'INR',
        ], self::TEST_KEY);

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest($body));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('20260829111212800110168559404001025', $verified['transaction_id']);
        $this->assertSame('100.00', $verified['amount']);
    }

    public function testCallbackAmountInMajorUnits(): void
    {
        // TXNAMOUNT 为主货币单位（实测回调 "10.00" 即 INR 10），与订单金额直接比对，不做换算
        $gateway = new PaytmGateway();
        $body = $this->callbackBody([
            'MID'       => 'TESTMID123',
            'ORDERID'   => 'DEP1',
            'TXNID'     => 'TXN1',
            'TXNAMOUNT' => '10.00',
            'STATUS'    => 'TXN_SUCCESS',
        ], self::TEST_KEY);

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest($body));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertTrue($verified['valid']);
        $this->assertSame('10.00', $verified['amount']);
    }

    public function testCallbackWrongKeyInvalid(): void
    {
        $gateway = new PaytmGateway();
        $body = $this->callbackBody([
            'ORDERID' => 'DEP1',
            'TXNID'   => 'TXN1',
            'STATUS'  => 'TXN_SUCCESS',
        ], 'wrong-secret');

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest($body));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertFalse($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCallbackTamperedParamsInvalid(): void
    {
        $gateway = new PaytmGateway();
        $params = ['ORDERID' => 'DEP1', 'TXNID' => 'TXN1', 'TXNAMOUNT' => '50.00', 'STATUS' => 'TXN_SUCCESS'];
        $params['CHECKSUMHASH'] = PaytmGateway::signNvp($params, self::TEST_KEY);
        $params['ORDERID'] = 'DEP_TAMPERED'; // 签名后篡改订单号

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(http_build_query($params)));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertFalse($verified['valid']);
    }

    public function testCallbackMissingKeyFailClosed(): void
    {
        $gateway = new PaytmGateway();
        $body = $this->callbackBody(['ORDERID' => 'DEP1', 'STATUS' => 'TXN_SUCCESS'], self::TEST_KEY);

        putenv('PAYTM_KEY');
        $verified = $gateway->verifyCallback($this->makeRequest($body));

        $this->assertFalse($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCallbackMissingChecksumInvalid(): void
    {
        $gateway = new PaytmGateway();
        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest('ORDERID=DEP1&STATUS=TXN_SUCCESS'));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertFalse($verified['valid']);
    }

    public function testCallbackFailureMapsToFailed(): void
    {
        $gateway = new PaytmGateway();
        $body = $this->callbackBody([
            'ORDERID' => 'DEP1',
            'TXNID'   => 'TXN1',
            'STATUS'  => 'TXN_FAILURE',
        ], self::TEST_KEY);

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest($body));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertTrue($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCallbackPendingIgnored(): void
    {
        $gateway = new PaytmGateway();
        $body = $this->callbackBody([
            'ORDERID' => 'DEP1',
            'TXNID'   => 'TXN1',
            'STATUS'  => 'PENDING',
        ], self::TEST_KEY);

        putenv('PAYTM_KEY=' . self::TEST_KEY);
        try {
            $verified = $gateway->verifyCallback($this->makeRequest($body));
        } finally {
            putenv('PAYTM_KEY');
        }

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testGatewayFactoryResolvesPaytm(): void
    {
        $this->assertInstanceOf(PaytmGateway::class, GatewayFactory::resolve('paytm'));
    }
}
