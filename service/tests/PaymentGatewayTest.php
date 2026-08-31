<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\CountryConfig;
use app\payment\AstroPayGateway;
use app\payment\CoinbaseCommerceGateway;
use app\payment\GatewayFactory;
use app\payment\GcashGateway;
use app\payment\KakaoPayGateway;
use app\payment\MercadoPagoGateway;
use app\payment\MpesaGateway;
use app\payment\NetellerGateway;
use app\payment\NowPaymentsGateway;
use app\payment\PayPalGateway;
use app\payment\PayPayGateway;
use app\payment\PaysafecardGateway;
use app\payment\PaystackGateway;
use app\payment\PaytmGateway;
use app\payment\SkrillGateway;
use app\payment\StripeGateway;
use app\payment\TossGateway;
use PHPUnit\Framework\TestCase;
use support\Request;

class PaymentGatewayTest extends TestCase
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

    public function testNowPaymentsFinishedParsedAsSuccess(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"finished","order_id":"DEP20260829153000ABC123","payment_id":"12345","price_amount":"100.0000"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('12345', $verified['transaction_id']);
        $this->assertSame('100.0000', $verified['amount']);
    }

    public function testNowPaymentsWaitingIgnored(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"waiting","order_id":"DEP1","payment_id":"1"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testNowPaymentsFailedMapsToFailed(): void
    {
        $gateway = new NowPaymentsGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"payment_status":"failed","order_id":"DEP1","payment_id":"1"}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('failed', $verified['status']);
    }

    public function testCoinbaseConfirmedParsedAsSuccess(): void
    {
        $gateway = new CoinbaseCommerceGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event":{"type":"charge:confirmed","data":{"id":"cb-1","metadata":{"order_no":"DEP20260829153000ABC123"},"pricing":{"local":{"amount":"50.00"}}}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('cb-1', $verified['transaction_id']);
        $this->assertSame('50.00', $verified['amount']);
    }

    public function testCoinbaseCreatedIgnored(): void
    {
        $gateway = new CoinbaseCommerceGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"event":{"type":"charge:created"},"data":{"id":"cb-1","metadata":{"order_no":"DEP1"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testStripeCheckoutCompletedParsedAsSuccess(): void
    {
        $gateway = new StripeGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"type":"checkout.session.completed","data":{"object":{"id":"cs_test_1","metadata":{"order_no":"DEP20260829153000ABC123"},"amount_total":10000,"currency":"usd","payment_status":"paid"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('success', $verified['status']);
        $this->assertSame('DEP20260829153000ABC123', $verified['order_no']);
        $this->assertSame('cs_test_1', $verified['transaction_id']);
        $this->assertSame('100.0000', $verified['amount']);
    }

    public function testStripeCompletedUnpaidIgnored(): void
    {
        $gateway = new StripeGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"type":"checkout.session.completed","data":{"object":{"id":"cs_test_1","metadata":{"order_no":"DEP1"},"amount_total":10000,"currency":"usd","payment_status":"unpaid"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testStripeOtherEventIgnored(): void
    {
        $gateway = new StripeGateway();
        $verified = $gateway->verifyCallback($this->makeRequest(
            '{"type":"checkout.session.expired","data":{"object":{"id":"cs_test_1"}}}'
        ));

        $this->assertTrue($verified['valid']);
        $this->assertSame('ignored', $verified['status']);
    }

    public function testToMinorZeroDecimalCurrencies(): void
    {
        $gateway = new StripeGateway();

        $this->assertSame('10000', $gateway->toMinor('100.00', 'USD'));
        $this->assertSame('100', $gateway->toMinor('100', 'JPY'));
        $this->assertSame('100', $gateway->toMinor('100.00', 'KRW'));
    }

    public function testNowPaymentsSignatureVerification(): void
    {
        $secret = 'test-ipn-secret';
        $body   = '{"payment_status":"finished","order_id":"DEP1","payment_id":"1"}';
        $sig    = hash_hmac('sha512', $body, $secret);

        putenv("NOWPAYMENTS_IPN_SECRET={$secret}");
        try {
            $this->assertTrue($this->verifyNowPaymentsSignature($body, $sig));
            $this->assertFalse($this->verifyNowPaymentsSignature($body, hash_hmac('sha512', $body, 'wrong-secret')));
            $this->assertFalse($this->verifyNowPaymentsSignature($body, ''));
        } finally {
            putenv('NOWPAYMENTS_IPN_SECRET');
        }
    }

    public function testCoinbaseSignatureVerification(): void
    {
        // 官方算法：shared secret 原样作 HMAC key，签名为 hex digest（非 base64）
        $secret = 'test-cc-secret';
        $body   = '{"event":{"type":"charge:confirmed"}}';
        $sig    = hash_hmac('sha256', $body, $secret);

        putenv("COINBASE_COMMERCE_WEBHOOK_SECRET={$secret}");
        try {
            $this->assertTrue($this->verifyCoinbaseSignature($body, $sig));
            $this->assertFalse($this->verifyCoinbaseSignature($body, hash_hmac('sha256', $body, 'wrong')));
            $this->assertFalse($this->verifyCoinbaseSignature($body, ''));
        } finally {
            putenv('COINBASE_COMMERCE_WEBHOOK_SECRET');
        }
    }

    public function testCountryConfigFromLangMapping(): void
    {
        $this->assertSame('CN', CountryConfig::fromLang('zh-CN'));
        $this->assertSame('JP', CountryConfig::fromLang('ja'));
        $this->assertSame('KR', CountryConfig::fromLang('ko-KR'));
        $this->assertSame('BR', CountryConfig::fromLang('pt-BR'));
        $this->assertSame('IN', CountryConfig::fromLang('hi-IN'));
        $this->assertSame('DE', CountryConfig::fromLang('de-DE'));
        $this->assertSame('US', CountryConfig::fromLang('en-US'));
        $this->assertSame('', CountryConfig::fromLang('xx-YY'));
        $this->assertSame('', CountryConfig::fromLang(''));
    }

    public function testGatewayFactoryResolvesProviders(): void
    {
        $this->assertInstanceOf(NowPaymentsGateway::class, GatewayFactory::resolve('nowpayments'));
        $this->assertInstanceOf(CoinbaseCommerceGateway::class, GatewayFactory::resolve('coinbase'));
        $this->assertInstanceOf(StripeGateway::class, GatewayFactory::resolve('stripe'));
        $this->assertInstanceOf(PayPalGateway::class, GatewayFactory::resolve('paypal'));
        $this->assertInstanceOf(SkrillGateway::class, GatewayFactory::resolve('skrill'));
        $this->assertInstanceOf(NetellerGateway::class, GatewayFactory::resolve('neteller'));
        $this->assertInstanceOf(PaysafecardGateway::class, GatewayFactory::resolve('paysafecard'));
        $this->assertInstanceOf(PaytmGateway::class, GatewayFactory::resolve('paytm'));
        $this->assertInstanceOf(MercadoPagoGateway::class, GatewayFactory::resolve('mercadopago'));
        $this->assertInstanceOf(AstroPayGateway::class, GatewayFactory::resolve('astropay'));
        $this->assertInstanceOf(PayPayGateway::class, GatewayFactory::resolve('paypay'));
        $this->assertInstanceOf(KakaoPayGateway::class, GatewayFactory::resolve('kakaopay'));
        $this->assertInstanceOf(GcashGateway::class, GatewayFactory::resolve('gcash'));
        $this->assertInstanceOf(MpesaGateway::class, GatewayFactory::resolve('mpesa'));
        $this->assertInstanceOf(PaystackGateway::class, GatewayFactory::resolve('paystack'));
        $this->assertInstanceOf(TossGateway::class, GatewayFactory::resolve('toss'));

        $this->expectException(\InvalidArgumentException::class);
        GatewayFactory::resolve('bitcoin');
    }

    public function testSkrillSignatureVerification(): void
    {
        $secret     = 'skrill-secret';
        $merchantId = '1392345';
        $sig        = static fn (string $tx, string $amount, string $currency, string $status): string =>
            strtoupper(md5($merchantId . $tx . strtoupper(md5($secret)) . $amount . $currency . $status));
        $body = fn (string $status, string $md5sig): string => http_build_query([
            'merchant_id'       => $merchantId,
            'transaction_id'    => 'DEP1',
            'mb_transaction_id' => '5585262',
            'mb_amount'         => '100.00',
            'mb_currency'       => 'EUR',
            'status'            => $status,
            'md5sig'            => $md5sig,
        ]);

        putenv("SKRILL_SECRET_WORD={$secret}");
        putenv("SKRILL_MERCHANT_ID={$merchantId}");
        try {
            $gateway = new SkrillGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($body('2', $sig('DEP1', '100.00', 'EUR', '2'))));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($body('2', $sig('DEP1', '100.00', 'EUR', '0'))))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($body('2', '')))['valid']);
        } finally {
            putenv('SKRILL_SECRET_WORD');
            putenv('SKRILL_MERCHANT_ID');
        }
    }

    public function testNetellerSignatureVerification(): void
    {
        $secret = 'neteller-secret';
        $body   = static fn (string $key): string => json_encode([
            'eventType'      => 'payment_succeeded',
            'transaction_ref' => 'DEP1',
            'id'             => 'n1',
            'amount'         => '50.00',
            'key'            => $key,
        ]);

        putenv("NETELLER_SECRET={$secret}");
        try {
            $gateway = new NetellerGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($body($secret)));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($body('wrong-key')))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($body('')))['valid']);
        } finally {
            putenv('NETELLER_SECRET');
        }
    }

    public function testPaysafecardSignatureVerification(): void
    {
        $secret = 'psc-secret';
        $json   = '{"id":"ps1","amount":"50.00","status":"AUTHORIZED","customer":{"id":"DEP1"}}';
        $sig    = base64_encode(hash_hmac('sha256', $json, $secret, true));

        putenv("PAYSAFECARD_SECRET={$secret}");
        try {
            $gateway = new PaysafecardGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($json, ['X-Signature' => $sig]));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json, ['X-Signature' => base64_encode(hash_hmac('sha256', $json, 'wrong', true))]))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json))['valid']);
        } finally {
            putenv('PAYSAFECARD_SECRET');
        }
    }

    public function testPaytmSignatureVerification(): void
    {
        $secret = 'paytm-secret';
        $params = ['ORDERID' => 'DEP1', 'TXNID' => 't1', 'TXNAMOUNT' => '100.00', 'STATUS' => 'TXN_SUCCESS'];
        $params['CHECKSUMHASH'] = PaytmGateway::signNvp($params, $secret);
        $validBody = http_build_query($params);

        putenv("PAYTM_KEY={$secret}");
        try {
            $gateway = new PaytmGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($validBody));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $bad = $params;
            $bad['CHECKSUMHASH'] = PaytmGateway::signNvp($params, 'wrong-key');
            $this->assertFalse($gateway->verifyCallback($this->makeRequest(http_build_query($bad)))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest(http_build_query(['ORDERID' => 'DEP1', 'TXNID' => 't1', 'TXNAMOUNT' => '100.00', 'STATUS' => 'TXN_SUCCESS'])))['valid']);
        } finally {
            putenv('PAYTM_KEY');
        }
    }

    public function testMercadoPagoSignatureVerification(): void
    {
        $secret    = 'mp-secret';
        $requestId = 'req-1';
        $ts        = time();
        $v1        = hash_hmac('sha256', "id:mp1;request-id:{$requestId};ts:{$ts};", $secret);
        $headers   = ['X-Signature' => "ts={$ts},v1={$v1}", 'X-Request-ID' => $requestId];

        putenv("MERCADOPAGO_WEBHOOK_SECRET={$secret}");
        try {
            $gateway = new MercadoPagoGateway();
            // 非 payment 事件：验签通过后直接 ignored（不触发回查支付单 API，零网络）
            $verified = $gateway->verifyCallback($this->makeRequest('{"type":"notification","data":{"id":"mp1"}}', $headers, 'POST', '/?data_id=mp1'));
            $this->assertTrue($verified['valid']);
            $this->assertSame('ignored', $verified['status']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest('{"type":"notification","data":{"id":"mp1"}}', ['X-Signature' => 'ts=' . $ts . ',v1=' . str_repeat('0', 64), 'X-Request-ID' => $requestId], 'POST', '/?data_id=mp1'))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest('{"type":"notification","data":{"id":"mp1"}}', [], 'POST', '/?data_id=mp1'))['valid']);
        } finally {
            putenv('MERCADOPAGO_WEBHOOK_SECRET');
        }
    }

    public function testAstroPaySignatureVerification(): void
    {
        $secret = 'astropay-secret';
        $data   = ['order_id' => 'DEP1', 'payment_id' => 'ap1', 'amount' => '50.00', 'status' => 'success'];
        $data['signature'] = md5($data['order_id'] . $data['amount'] . $data['status'] . $secret);

        putenv("ASTROPAY_SECRET={$secret}");
        try {
            $gateway = new AstroPayGateway();
            $verified = $gateway->verifyCallback($this->makeRequest(json_encode($data)));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $bad = $data;
            $bad['signature'] = md5('wrong');
            $this->assertFalse($gateway->verifyCallback($this->makeRequest(json_encode($bad)))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest(json_encode(['order_id' => 'DEP1', 'amount' => '50.00', 'status' => 'success'])))['valid']);
        } finally {
            putenv('ASTROPAY_SECRET');
        }
    }

    public function testPayPaySignatureVerification(): void
    {
        $secret = 'paypay-secret';
        $json   = '{"event":"PAYMENT_CAPTURED","data":{"merchantPaymentId":"DEP1","paymentId":"pp1","amount":{"amount":5000}}}';
        $ts     = time();
        $sig    = 'hash=' . hash_hmac('sha256', $json . (string) $ts, $secret) . ',ts=' . $ts;

        putenv("PAYPAY_SIGNING_KEY={$secret}");
        try {
            $gateway = new PayPayGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($json, ['PayPay-Signature' => $sig]));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $bad = 'hash=' . hash_hmac('sha256', $json . (string) $ts, 'wrong') . ',ts=' . $ts;
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json, ['PayPay-Signature' => $bad]))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json))['valid']);
        } finally {
            putenv('PAYPAY_SIGNING_KEY');
        }
    }

    public function testGcashSignatureVerification(): void
    {
        $secret = 'paymongo-secret';
        $json   = '{"data":{"attributes":{"type":"payment.succeeded","data":{"id":"pm1","attributes":{"amount":5000,"metadata":{"order_no":"DEP1"}}}}}}';
        $ts     = time();
        $sig    = 't=' . $ts . ',te=,li=' . hash_hmac('sha256', $ts . '.' . $json, $secret);

        putenv("PAYMONGO_WEBHOOK_SECRET={$secret}");
        try {
            $gateway = new GcashGateway();
            $verified = $gateway->verifyCallback($this->makeRequest($json, ['Paymongo-Signature' => $sig]));
            $this->assertTrue($verified['valid']);
            $this->assertSame('success', $verified['status']);
            $this->assertSame('50.0000', $verified['amount']);
            $bad = 't=' . $ts . ',te=,li=' . hash_hmac('sha256', $ts . '.' . $json, 'wrong');
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json, ['Paymongo-Signature' => $bad]))['valid']);
            $this->assertFalse($gateway->verifyCallback($this->makeRequest($json))['valid']);
        } finally {
            putenv('PAYMONGO_WEBHOOK_SECRET');
        }
    }

    public function testKakaoPayFlatParamSecurity(): void
    {
        $gateway = new KakaoPayGateway();
        // 前端上报 failed 直接透传（无需 pg_token/approve）
        $failed = $gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=kakaopay&order_no=DEP1&transaction_id=t1&status=failed'));
        $this->assertTrue($failed['valid']);
        $this->assertSame('failed', $failed['status']);
        // success 但缺 pg_token：无法 approve，拒绝
        $noToken = $gateway->verifyCallback($this->makeRequest('', [], 'POST', '/?provider=kakaopay&order_no=DEP1&transaction_id=t1&status=success'));
        $this->assertFalse($noToken['valid']);
    }

    private function verifyNowPaymentsSignature(string $body, string $signature): bool
    {
        $controller = new \app\api\v1\controller\PaymentController();
        $method = new \ReflectionMethod($controller, 'verifyNowPaymentsSignature');
        $request = $this->makeRequest($body, ['X-NowPayments-Sig' => $signature]);
        return $method->invoke($controller, $request);
    }

    private function verifyCoinbaseSignature(string $body, string $signature): bool
    {
        $controller = new \app\api\v1\controller\PaymentController();
        $method = new \ReflectionMethod($controller, 'verifyCoinbaseSignature');
        $request = $this->makeRequest($body, ['X-CC-Webhook-Signature' => $signature]);
        return $method->invoke($controller, $request);
    }
}
