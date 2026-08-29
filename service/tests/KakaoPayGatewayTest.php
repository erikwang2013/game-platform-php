<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\DepositOrder;
use app\payment\KakaoPayGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class KakaoPayGatewayTest extends TestCase
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

    /** 以内存订单 + 替身 approve 构建网关，零网络零数据库 */
    private function gateway(array $approve, string $orderNo, string $amount, ?\Throwable $approveError = null, string $orderStatus = 'pending', string $orderTxId = ''): KakaoPayGateway
    {
        $order = new DepositOrder();
        $order->order_no = $orderNo;
        $order->amount = $amount;
        $order->user_id = 1;
        $order->status = $orderStatus;
        $order->transaction_id = $orderTxId;

        return new class($approve, $approveError, $order) extends KakaoPayGateway {
            private $approve;
            private $approveError;
            private $order;

            public function __construct(array $approve, ?\Throwable $approveError, DepositOrder $order)
            {
                $this->approve = $approve;
                $this->approveError = $approveError;
                $this->order = $order;
            }

            protected function callApprove(array $params): array
            {
                if ($this->approveError !== null) {
                    throw $this->approveError;
                }
                return $this->approve;
            }

            protected function findOrder(string $orderNo): ?DepositOrder
            {
                return $this->order;
            }
        };
    }

    public function testApproveSuccessWhenAmountMatches(): void
    {
        $gateway = $this->gateway(
            ['tid' => 'tid-1', 'amount' => ['total' => 10000]],
            'DEP20260829153000ABC123',
            '10000'
        );

        putenv('KAKAOPAY_ADMIN_KEY=test-admin-key');
        putenv('KAKAOPAY_CID=TC0ONETIME');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                '',
                [],
                'GET',
                '/api/payment/callback?provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=success&pg_token=pg-1'
            ));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('tid-1', $verified['transaction_id']);
            $this->assertSame('10000', $verified['amount']);
        } finally {
            putenv('KAKAOPAY_ADMIN_KEY');
            putenv('KAKAOPAY_CID');
        }
    }

    public function testApproveAmountMismatchRejected(): void
    {
        $gateway = $this->gateway(
            ['tid' => 'tid-1', 'amount' => ['total' => 9999]],
            'DEP20260829153000ABC123',
            '10000'
        );

        putenv('KAKAOPAY_ADMIN_KEY=test-admin-key');
        putenv('KAKAOPAY_CID=TC0ONETIME');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                '',
                [],
                'GET',
                '/api/payment/callback?provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=success&pg_token=pg-1'
            ));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('KAKAOPAY_ADMIN_KEY');
            putenv('KAKAOPAY_CID');
        }
    }

    public function testApproveErrorRejected(): void
    {
        $gateway = $this->gateway(
            [],
            'DEP20260829153000ABC123',
            '10000',
            new \RuntimeException('approve api 400')
        );

        putenv('KAKAOPAY_ADMIN_KEY=test-admin-key');
        putenv('KAKAOPAY_CID=TC0ONETIME');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                'provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=success&pg_token=pg-1'
            ));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('KAKAOPAY_ADMIN_KEY');
            putenv('KAKAOPAY_CID');
        }
    }

    public function testMissingPgTokenRejected(): void
    {
        $gateway = $this->gateway(
            ['tid' => 'tid-1', 'amount' => ['total' => 10000]],
            'DEP20260829153000ABC123',
            '10000'
        );

        putenv('KAKAOPAY_ADMIN_KEY=test-admin-key');
        putenv('KAKAOPAY_CID=TC0ONETIME');
        try {
            $verified = $gateway->verifyCallback($this->makeRequest(
                '',
                [],
                'GET',
                '/api/payment/callback?provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=success'
            ));

            $this->assertFalse($verified['valid']);
        } finally {
            putenv('KAKAOPAY_ADMIN_KEY');
            putenv('KAKAOPAY_CID');
        }
    }

    public function testFailedStatusPassedThroughWithoutApprove(): void
    {
        // approveError 非空：若代码误调 approve 会抛异常 → 测试失败，证明未调 approve
        $gateway = $this->gateway(
            [],
            'DEP20260829153000ABC123',
            '10000',
            new \RuntimeException('approve must not be called')
        );

        $verified = $gateway->verifyCallback($this->makeRequest(
            '',
            [],
            'GET',
            '/api/payment/callback?provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=failed'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testAlreadyProcessedShortCircuitsWithoutApprove(): void
    {
        // 订单已 confirmed 且 tid 相同：直接返回成功，不重复调 approve
        $gateway = $this->gateway(
            [],
            'DEP20260829153000ABC123',
            '10000',
            new \RuntimeException('approve must not be called'),
            'confirmed',
            'tid-1'
        );

        $verified = $gateway->verifyCallback($this->makeRequest(
            '',
            [],
            'GET',
            '/api/payment/callback?provider=kakaopay&order_no=DEP20260829153000ABC123&transaction_id=tid-1&status=success&pg_token=pg-1'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
    }

    public function testMissingParamsRejected(): void
    {
        $gateway = $this->gateway(
            ['tid' => 'tid-1', 'amount' => ['total' => 10000]],
            'DEP1',
            '10000'
        );

        $verified = $gateway->verifyCallback($this->makeRequest('', [], 'GET', '/api/payment/callback?provider=kakaopay&order_no=&transaction_id=tid-1&status=success'));

        $this->assertFalse($verified['valid']);
    }
}
