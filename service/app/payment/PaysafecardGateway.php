<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use support\Request;

/**
 * Paysafecard 预付卡支付网关：v1 Payments API 生成支付面板跳转链接，
 * notification_url 回调带 Signature 头（HMAC-SHA256(密钥, 原始请求体) 的 base64）。
 */
class PaysafecardGateway implements PaymentGatewayInterface
{
    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $apiKey = getenv('PAYSAFECARD_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \RuntimeException('PAYSAFECARD_API_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('PAYSAFECARD_API_URL') ?: 'https://api.paysafecard.com', '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = (new \GuzzleHttp\Client(['timeout' => 15, 'auth' => [$apiKey, '']]))
            ->post($apiUrl . '/v1/payments', [
                'json' => [
                    'amount'           => $order->amount,
                    'currency'         => $order->currency,
                    'customer_id'      => $order->order_no,
                    'country'          => strtoupper((string) ($method->config['country'] ?? 'DE')),
                    'redirect'         => [
                        'success_url' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                        'failure_url' => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                        'cancel_url'  => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                    ],
                    'notification_url' => $siteUrl . '/api/payment/callback?provider=paysafecard',
                ],
            ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['id']) || empty($data['redirect']['auth_url'])) {
            throw new \RuntimeException('Paysafecard payment failed: ' . ($data['error'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['redirect']['auth_url'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $raw  = $request->rawBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $secret = getenv('PAYSAFECARD_SECRET') ?: '';
        $sig    = (string) ($request->header('x-signature', '') ?: $request->header('signature', ''));
        if ($secret === '' || $sig === '') {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $expected = base64_encode(hash_hmac('sha256', $raw, $this->secretKey($secret), true));
        if (!hash_equals($expected, $sig)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $mapped = match ((string) ($data['status'] ?? '')) {
            'AUTHORIZED', 'SUCCESS' => 'success',
            'FAILED', 'CANCELLED', 'EXPIRED' => 'failed',
            default => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($data['customer']['id'] ?? ''),
            'transaction_id' => (string) ($data['id'] ?? ''),
            'amount'         => (string) ($data['amount'] ?? ''),
            'status'         => $mapped,
        ];
    }

    /** Paysafe 分发的 HMAC 密钥可能为 base64 形态，两种都支持 */
    private function secretKey(string $secret): string
    {
        $decoded = base64_decode($secret, true);
        return $decoded !== false && base64_encode($decoded) === $secret ? $decoded : $secret;
    }
}
