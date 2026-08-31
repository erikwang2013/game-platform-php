<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\DepositOrder;
use app\payment\MpesaGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class MpesaGatewayTest extends TestCase
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

    /** 无签名回调：测试通过子类替换订单查找，返回内存订单，零 DB 零网络 */
    private function gatewayWithOrder(?DepositOrder $order): MpesaGateway
    {
        return new class ($order) extends MpesaGateway {
            private ?DepositOrder $order;

            public function __construct(?DepositOrder $order)
            {
                $this->order = $order;
            }

            protected function findOrderByCheckoutRequest(string $checkoutId): ?DepositOrder
            {
                return $this->order;
            }
        };
    }

    private function fakeOrder(string $orderNo, string $amount): DepositOrder
    {
        return new DepositOrder(['order_no' => $orderNo, 'amount' => $amount]);
    }

    public function testSuccessfulCallbackParsedAsSuccess(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $body = '{"Body":{"stkCallback":{"MerchantRequestID":"29115-34620561-1","CheckoutRequestID":"ws_CO_191220191020363925","ResultCode":0,"ResultDesc":"The service request is processed successfully.","CallbackMetadata":{"Item":[{"Name":"Amount","Value":100.00},{"Name":"MpesaReceiptNumber","Value":"NLJ7RT61SV"},{"Name":"PhoneNumber","Value":254712345678}]}}}}';
            $gateway = $this->gatewayWithOrder($this->fakeOrder('DEP20260829153000ABC123', '100.0000'));

            $verified = $gateway->verifyCallback($this->makeRequest($body));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
            $this->assertSame('ws_CO_191220191020363925', $verified['transaction_id']);
            $this->assertSame('100', $verified['amount']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testNonZeroResultCodeMapsToFailed(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $body = '{"Body":{"stkCallback":{"MerchantRequestID":"29115-34620561-1","CheckoutRequestID":"ws_CO_191220191020363925","ResultCode":1032,"ResultDesc":"Request cancelled by user."}}}';
            $gateway = $this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'));

            $verified = $gateway->verifyCallback($this->makeRequest($body));

            $this->assertTrue($verified['valid']);
            $this->assertSame('failed', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testUnknownCheckoutRequestIdRejected(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $body = '{"Body":{"stkCallback":{"CheckoutRequestID":"ws_CO_unknown","ResultCode":0,"CallbackMetadata":{"Item":[{"Name":"Amount","Value":100.00}]}}}}';
            // 查无此单：无签名回调 fail-closed
            $this->assertFalse($this->gatewayWithOrder(null)->verifyCallback($this->makeRequest($body))['valid']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testMissingStkCallbackRejected(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest('{}'))['valid']);
            $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest('{"Body":{}}'))['valid']);
            $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest('not-json'))['valid']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testMissingCheckoutRequestIdRejected(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $body = '{"Body":{"stkCallback":{"ResultCode":0,"CallbackMetadata":{"Item":[{"Name":"Amount","Value":100.00}]}}}}';
            $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest($body))['valid']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testSuccessWithoutConfirmedAmountRejected(): void
    {
        putenv('MPESA_SHORTCODE=174379');
        try {
            $body = '{"Body":{"stkCallback":{"CheckoutRequestID":"ws_CO_191220191020363925","ResultCode":0,"ResultDesc":"success"}}}';
            $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest($body))['valid']);
        } finally {
            putenv('MPESA_SHORTCODE');
        }
    }

    public function testMissingShortcodeFailsClosed(): void
    {
        $body = '{"Body":{"stkCallback":{"CheckoutRequestID":"ws_CO_1","ResultCode":0,"CallbackMetadata":{"Item":[{"Name":"Amount","Value":100.00}]}}}}';
        $this->assertFalse($this->gatewayWithOrder($this->fakeOrder('DEP1', '100.0000'))->verifyCallback($this->makeRequest($body))['valid']);
    }
}
