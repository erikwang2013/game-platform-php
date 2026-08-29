<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use support\Request;

/**
 * PayPay 日本支付网关：OAuth2 client_credentials 取 token 后创建 QR 订单（WEB_LINK 跳转）。
 * Webhook 验签：PayPay-Signature: hash=<hex>,ts=<unix>，HMAC-SHA256(原始报文 . ts)，密钥为 signing key。
 */
class PayPayGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.paypay.ne.jp';

    /** 签名时间戳允许偏差（秒），与 PayPay 官方示例一致 */
    private const TS_TOLERANCE = 1800;

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $clientId     = getenv('PAYPAY_CLIENT_ID') ?: '';
        $clientSecret = getenv('PAYPAY_CLIENT_SECRET') ?: '';
        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('PAYPAY_CLIENT_ID/PAYPAY_CLIENT_SECRET not configured');
        }
        $apiUrl  = rtrim(getenv('PAYPAY_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $http = new \GuzzleHttp\Client(['timeout' => 15]);

        $tokenResp = $http->post($apiUrl . '/v1/oauth2/token', [
            'headers'     => ['Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret)],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $tokenData   = json_decode((string) $tokenResp->getBody(), true);
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        if ($accessToken === '') {
            throw new \RuntimeException('PayPay OAuth token failed');
        }

        $resp = $http->post($apiUrl . '/v2/orders', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'json'    => [
                'merchantPaymentId' => $order->order_no,
                'amount'            => ['amount' => (int) $order->amount, 'currency' => 'JPY'],
                'codeType'          => 'ORDER_QR',
                'orderDescription'  => 'Game deposit ' . $order->order_no,
                'redirectUrl'       => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                'redirectType'      => 'WEB_LINK',
                'isAuthorization'   => false,
            ],
        ]);
        $data    = json_decode((string) $resp->getBody(), true);
        $created = is_array($data['data'] ?? null) ? $data['data'] : [];

        $checkoutUrl = '';
        foreach ((array) ($created['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'redirect') {
                $checkoutUrl = (string) ($link['url'] ?? '');
                break;
            }
        }
        $transactionId = (string) ($created['orderId'] ?? $created['merchantPaymentId'] ?? '');
        if ($checkoutUrl === '' || $transactionId === '') {
            throw new \RuntimeException('PayPay order create failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => $checkoutUrl,
            'transaction_id' => $transactionId,
        ];
    }

    public function verifyCallback(Request $request): array
    {
        if (!$this->verifySignature($request)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $data = json_decode($request->rawBody(), true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $mapped = match ((string) ($data['event'] ?? '')) {
            'PAYMENT_CAPTURED' => 'success',
            'PAYMENT_FAILED'   => 'failed',
            default            => 'ignored',
        };

        $payload     = is_array($data['data'] ?? null) ? $data['data'] : [];
        $orderPart   = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $paymentPart = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $amountPart  = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];
        $payAmount   = is_array($paymentPart['amount'] ?? null) ? $paymentPart['amount'] : [];

        return [
            'valid'          => true,
            'order_no'       => (string) ($payload['merchantPaymentId'] ?? $orderPart['merchantPaymentId'] ?? $paymentPart['merchantPaymentId'] ?? ''),
            'transaction_id' => (string) ($payload['paymentId'] ?? $paymentPart['paymentId'] ?? ''),
            'amount'         => (string) ($amountPart['amount'] ?? $payAmount['amount'] ?? ''),
            'status'         => $mapped,
        ];
    }

    /**
     * 验签：签名串 = 原始报文 . ts，HMAC-SHA256，hex 对比；ts 超 30 分钟视为重放拒绝。
     */
    private function verifySignature(Request $request): bool
    {
        $secret    = getenv('PAYPAY_SIGNING_KEY') ?: '';
        $signature = $request->header('PayPay-Signature', '') ?: $request->header('X-PayPay-Signature', '');
        if (!$secret || !$signature) {
            return false;
        }
        parse_str(str_replace(',', '&', $signature), $parts);
        $hash = (string) ($parts['hash'] ?? '');
        $ts   = (int) ($parts['ts'] ?? 0);
        if ($hash === '' || $ts <= 0 || abs(time() - $ts) > self::TS_TOLERANCE) {
            return false;
        }
        $expected = hash_hmac('sha256', $request->rawBody() . (string) $ts, $secret);
        return hash_equals($expected, $hash);
    }
}
