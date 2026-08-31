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
 * Neteller 电子钱包支付网关：OAuth2 client_credentials + transfer/payments 生成跳转支付页，
 * webhook 通知体携带 key 字段（商户门户配置的 Secret Key）做验签。
 */
class NetellerGateway implements PaymentGatewayInterface
{
    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $clientId     = getenv('NETELLER_CLIENT_ID') ?: '';
        $clientSecret = getenv('NETELLER_CLIENT_SECRET') ?: '';
        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('NETELLER_CLIENT_ID/NETELLER_CLIENT_SECRET not configured');
        }
        $apiUrl  = rtrim(getenv('NETELLER_API_URL') ?: 'https://api.neteller.com', '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $tokenResp = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/v1/oauth2/token', [
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);
        $token = json_decode((string) $tokenResp->getBody(), true);
        $accessToken = (string) ($token['access_token'] ?? '');
        if (!$accessToken) {
            throw new \RuntimeException('Neteller OAuth token failed');
        }

        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/v1/transfer/payments', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'json'    => [
                'amount'          => $order->amount,
                'currency'        => $order->currency,
                'transaction_ref' => $order->order_no,
                'payer_email'     => (string) ($order->user->email ?? ''),
                'merchant_ref'    => 'game deposit ' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        $redirect = '';
        foreach ((array) ($data['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'redirect') {
                $redirect = (string) ($link['url'] ?? '');
            }
        }
        if (empty($data['id']) || $redirect === '') {
            throw new \RuntimeException('Neteller payment failed: ' . ($data['error'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => $redirect,
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $data = json_decode($request->rawBody(), true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $secret = getenv('NETELLER_SECRET') ?: '';
        $key    = (string) ($data['key'] ?? '');
        if ($secret === '' || $key === '' || !hash_equals($secret, $key)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $mapped = match ((string) ($data['eventType'] ?? '')) {
            'payment_succeeded', 'order_payment_succeeded', 'subscription_payment_succeeded' => 'success',
            'payment_declined', 'payment_cancelled', 'order_payment_declined', 'order_cancelled_or_expired' => 'failed',
            default => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($data['transaction_ref'] ?? ''),
            'transaction_id' => (string) ($data['id'] ?? ''),
            'amount'         => (string) ($data['amount'] ?? ''),
            'status'         => $mapped,
        ];
    }
}
