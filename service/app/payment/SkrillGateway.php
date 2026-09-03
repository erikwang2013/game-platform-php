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
 * Skrill 电子钱包支付网关：v3 Payments API 生成跳转支付页，status_url 表单回调 + md5sig 签名。
 */
class SkrillGateway implements PaymentGatewayInterface
{
    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $apiKey = getenv('SKRILL_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \RuntimeException('SKRILL_API_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('SKRILL_API_URL') ?: 'https://pay.skrill.com', '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = (new \GuzzleHttp\Client(['timeout' => 15, 'auth' => [$apiKey, '']]))
            ->post($apiUrl . '/v3/payments', [
                'json' => [
                    'transaction_id' => $order->order_no,
                    'amount'         => $order->amount,
                    'currency'       => $order->currency,
                    'customer_email' => (string) ($order->user->email ?? ''),
                    'status_url'     => $siteUrl . '/api/v1/payment/callback?provider=skrill',
                    'return_url'     => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                    'cancel_url'     => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                ],
            ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['id']) || empty($data['redirect_url'])) {
            throw new \RuntimeException('Skrill payment failed: ' . ($data['error'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['redirect_url'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $raw  = $request->rawBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            parse_str($raw, $data);
        }

        $secret    = getenv('SKRILL_SECRET_WORD') ?: '';
        $merchant  = getenv('SKRILL_MERCHANT_ID') ?: '';
        $sig       = (string) ($data['md5sig'] ?? '');
        if ($secret === '' || $merchant === '' || $sig === '') {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $expected = strtoupper(md5(
            $merchant
            . (string) ($data['transaction_id'] ?? '')
            . strtoupper(md5($secret))
            . (string) ($data['mb_amount'] ?? '')
            . (string) ($data['mb_currency'] ?? '')
            . (string) ($data['status'] ?? '')
        ));
        if (!hash_equals($expected, strtoupper($sig))) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $mapped = match ((string) ($data['status'] ?? '')) {
            '2'                    => 'success',
            '-1', '-2', '3'        => 'failed',
            default                => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($data['transaction_id'] ?? ''),
            'transaction_id' => (string) ($data['mb_transaction_id'] ?? ''),
            'amount'         => (string) ($data['mb_amount'] ?? ''),
            'status'         => $mapped,
        ];
    }
}
