<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use GuzzleHttp\Client;
use RuntimeException;
use support\Request;

/**
 * PayPal 支付网关：OAuth2 获取令牌后创建 v2 Checkout Order，用户跳转 approve 链接完成支付。
 * 回调验签由控制器完成（POST 回 PayPal verify-webhook-signature），此处仅解析报文。
 */
class PayPalGateway implements PaymentGatewayInterface
{
    private function apiBase(): string
    {
        return getenv('PAYPAL_MODE') === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function accessToken(Client $http): string
    {
        $clientId = getenv('PAYPAL_CLIENT_ID') ?: '';
        $secret   = getenv('PAYPAL_CLIENT_SECRET') ?: '';
        if (!$clientId || !$secret) {
            throw new RuntimeException('PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET not configured');
        }

        $resp = $http->post($this->apiBase() . '/v1/oauth2/token', [
            'auth'        => [$clientId, $secret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('PayPal token failed: ' . ($data['error_description'] ?? 'unknown'));
        }

        return (string) $data['access_token'];
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $http    = new Client(['timeout' => 15]);
        $token   = $this->accessToken($http);
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = $http->post($this->apiBase() . '/v2/checkout/orders', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'json'    => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->order_no,
                    'description'  => 'Game deposit ' . $order->order_no,
                    'amount' => [
                        'currency_code' => strtoupper($order->currency),
                        'value'         => $this->formatAmount($order->amount),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                    'cancel_url' => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                ],
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        $checkoutUrl = '';
        foreach (($data['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $checkoutUrl = (string) ($link['href'] ?? '');
            }
        }
        if (empty($data['id']) || $checkoutUrl === '') {
            throw new RuntimeException('PayPal order creation failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => $checkoutUrl,
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $data = json_decode($request->rawBody(), true);
        if (!is_array($data) || empty($data['event_type'])) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $mapped = match ((string) $data['event_type']) {
            'PAYMENT.CAPTURE.COMPLETED'                       => 'success',
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.FAILED',
            'PAYMENT.CAPTURE.REVERSED'                        => 'failed',
            default                                           => 'ignored',
        };

        $resource      = is_array($data['resource'] ?? null) ? $data['resource'] : [];
        $purchaseUnits = is_array($resource['purchase_units'] ?? null) ? $resource['purchase_units'] : [];

        return [
            'valid'          => true,
            'order_no'       => (string) ($purchaseUnits[0]['reference_id'] ?? ''),
            'transaction_id' => (string) ($resource['id'] ?? ''),
            'amount'         => (string) ($resource['amount']['value'] ?? ''),
            'status'         => $mapped,
        ];
    }

    /** PayPal 金额为十进制字符串，允许整数或 1-2 位小数：去掉尾部多余的 0 */
    private function formatAmount(string $amount): string
    {
        return rtrim(rtrim(bcadd($amount, '0', 2), '0'), '.');
    }
}
