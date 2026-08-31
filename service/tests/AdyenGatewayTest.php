<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use app\payment\AdyenGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use support\Request;

class AdyenGatewayTest extends TestCase
{
    private const HMAC_KEY = 'test-adyen-hmac-key';
    private const MERCHANT = 'TestMerchant';

    protected function makeRequest(string $body, array $headers = [], string $path = '/api/payment/callback?provider=adyen'): Request
    {
        $headerBlock = '';
        foreach ($headers as $name => $value) {
            $headerBlock .= "{$name}: {$value}\r\n";
        }
        $buffer = "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n{$headerBlock}\r\n{$body}";
        return new Request($buffer);
    }

    /**
     * 构造 Adyen webhook 报文并正确签名：hmacSignature 字段不参与签名计算，
     * 先对不含该字段的 item 计算 HMAC，再把结果放回报文。
     */
    private function signedWebhook(array $item): string
    {
        // 网关侧签名范围 = item 全字段（含 additionalData）减去 hmacSignature 本身；
        // additionalData 在真实报文中始终存在，测试也必须在签名前带上（否则重编码出现 []）。
        $item['additionalData'] ??= [];
        $expected = base64_encode(hash_hmac('sha256', json_encode($item), self::HMAC_KEY, true));
        $item['additionalData']['hmacSignature'] = $expected;
        return json_encode([
            'notificationItems' => [['NotificationRequestItem' => $item]],
        ]);
    }

    private function withEnv(array $env, callable $fn): void
    {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
        try {
            $fn();
        } finally {
            foreach ($env as $key => $_) {
                putenv($key);
            }
        }
    }

    private function gateway(): AdyenGateway
    {
        return new AdyenGateway(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
    }

    public function testCreatePaymentReturnsCheckoutUrl(): void
    {
        $gateway = new AdyenGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"id":"SESS-1","url":"https://checkout.adyen.com/session/abc"}'),
        ]))]));
        $this->withEnv(['ADYEN_API_KEY' => 'test-key', 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $result = $gateway->createPayment(
                new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
                new PaymentMethod()
            );
            $this->assertSame('https://checkout.adyen.com/session/abc', $result['checkout_url']);
            $this->assertSame('SESS-1', $result['transaction_id']);
        });
    }

    public function testCreatePaymentMissingEnvThrows(): void
    {
        putenv('ADYEN_API_KEY');
        putenv('ADYEN_MERCHANT_ACCOUNT');
        $this->expectException(\RuntimeException::class);
        $this->gateway()->createPayment(
            new DepositOrder(['order_no' => 'DEP1', 'amount' => '100.00', 'currency' => 'USD']),
            new PaymentMethod()
        );
    }

    public function testCallbackValidAuthorisation(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['ADYEN_HMAC_KEY' => self::HMAC_KEY, 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $body = $this->signedWebhook([
                'merchantAccount' => self::MERCHANT,
                'reference'       => 'DEP1',
                'pspReference'    => 'PSP-123',
                'eventCode'       => 'AUTHORISATION',
                'success'         => 'true',
                'amount'          => ['value' => 10000, 'currency' => 'USD'],
            ]);
            $verified = $gateway->verifyCallback($this->makeRequest($body));

            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('DEP1', $verified['order_no']);
            $this->assertSame('PSP-123', $verified['transaction_id']);
            $this->assertSame('100.0000', $verified['amount']);
        });
    }

    public function testCallbackZeroDecimalAmount(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['ADYEN_HMAC_KEY' => self::HMAC_KEY, 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $body = $this->signedWebhook([
                'merchantAccount' => self::MERCHANT,
                'reference'       => 'DEP2',
                'pspReference'    => 'PSP-124',
                'eventCode'       => 'AUTHORISATION',
                'success'         => 'true',
                'amount'          => ['value' => 500, 'currency' => 'JPY'],
            ]);
            $verified = $gateway->verifyCallback($this->makeRequest($body));

            $this->assertTrue($verified['valid']);
            $this->assertSame('500', $verified['amount']);
        });
    }

    public function testCallbackBadSignatureInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['ADYEN_HMAC_KEY' => self::HMAC_KEY, 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $body = $this->signedWebhook([
                'merchantAccount' => self::MERCHANT,
                'reference'       => 'DEP1',
                'pspReference'    => 'PSP-123',
                'eventCode'       => 'AUTHORISATION',
                'success'         => 'true',
                'amount'          => ['value' => 10000, 'currency' => 'USD'],
            ]);
            // 篡改报文后签名不匹配
            $tampered = str_replace('"reference":"DEP1"', '"reference":"DEPX"', $body);
            $verified = $gateway->verifyCallback($this->makeRequest($tampered));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testCallbackWrongMerchantInvalid(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['ADYEN_HMAC_KEY' => self::HMAC_KEY, 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            // 签名正确但 merchantAccount 是别的商户 → 拒绝（防跨商户 webhook 冒用）
            $body = $this->signedWebhook([
                'merchantAccount' => 'OtherMerchant',
                'reference'       => 'DEP1',
                'pspReference'    => 'PSP-123',
                'eventCode'       => 'AUTHORISATION',
                'success'         => 'true',
                'amount'          => ['value' => 10000, 'currency' => 'USD'],
            ]);
            $verified = $gateway->verifyCallback($this->makeRequest($body));
            $this->assertFalse($verified['valid']);
        });
    }

    public function testCallbackNonAuthorisationIgnored(): void
    {
        $gateway = $this->gateway();
        $this->withEnv(['ADYEN_HMAC_KEY' => self::HMAC_KEY, 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $body = $this->signedWebhook([
                'merchantAccount' => self::MERCHANT,
                'reference'       => 'DEP1',
                'pspReference'    => 'PSP-123',
                'eventCode'       => 'REFUND',
                'success'         => 'true',
                'amount'          => ['value' => 10000, 'currency' => 'USD'],
            ]);
            $verified = $gateway->verifyCallback($this->makeRequest($body));

            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
        });
    }

    public function testCallbackMissingSecretInvalid(): void
    {
        putenv('ADYEN_HMAC_KEY');
        putenv('ADYEN_MERCHANT_ACCOUNT');
        $body = $this->signedWebhook([
            'merchantAccount' => self::MERCHANT,
            'reference'       => 'DEP1',
            'pspReference'    => 'PSP-123',
            'eventCode'       => 'AUTHORISATION',
            'success'         => 'true',
        ]);
        $verified = $this->gateway()->verifyCallback($this->makeRequest($body));
        $this->assertFalse($verified['valid']);
    }

    public function testRefundSuccess(): void
    {
        $gateway = new AdyenGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"pspReference":"PSP-REF-1","status":"received"}'),
        ]))]));
        $this->withEnv(['ADYEN_API_KEY' => 'test-key', 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $result = $gateway->refund(
                new DepositOrder(['order_no' => 'DEP1', 'transaction_id' => 'PSP-123', 'currency' => 'USD']),
                '50.00'
            );
            $this->assertTrue($result['success']);
            $this->assertSame('PSP-REF-1', $result['refund_id']);
        });
    }

    public function testRefundRefusedThrows(): void
    {
        $gateway = new AdyenGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"status":"refused","refusalReason":"no funds"}'),
        ]))]));
        $this->withEnv(['ADYEN_API_KEY' => 'test-key', 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () use ($gateway) {
            $this->expectException(\RuntimeException::class);
            $gateway->refund(
                new DepositOrder(['order_no' => 'DEP1', 'transaction_id' => 'PSP-123', 'currency' => 'USD']),
                '50.00'
            );
        });
    }

    public function testQueryStatusMapping(): void
    {
        $this->withEnv(['ADYEN_API_KEY' => 'test-key', 'ADYEN_MERCHANT_ACCOUNT' => self::MERCHANT], function () {
            foreach ([
                'authorised' => 'confirmed',
                'received'   => 'confirmed',
                'refused'    => 'failed',
                'expired'    => 'failed',
                'unknown'    => 'pending',
            ] as $resultCode => $expectedStatus) {
                $gateway = new AdyenGateway(new Client(['handler' => HandlerStack::create(new MockHandler([
                    new Response(200, [], json_encode(['resultCode' => $resultCode, 'amount' => ['value' => 10000, 'currency' => 'USD']])),
                ]))]));
                $result = $gateway->query(new DepositOrder(['transaction_id' => 'PSP-123']));
                $this->assertSame($expectedStatus, $result['status']);
                $this->assertSame('100.0000', $result['amount']);
            }
        });
    }
}
