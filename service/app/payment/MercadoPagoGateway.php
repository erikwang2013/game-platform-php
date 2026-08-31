<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use GuzzleHttp\Client;
use support\Request;

/**
 * Mercado Pago 支付网关：OAuth client_credentials 取 token，Checkout Pro preferences
 * 生成托管支付页；webhook 验签（X-Signature: ts,v1）后回查支付单确认状态。
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.mercadopago.com';

    /** webhook 时间戳允许漂移（秒），超出视为重放 */
    private const TS_TOLERANCE = 300;

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $clientId     = getenv('MERCADOPAGO_CLIENT_ID') ?: '';
        $clientSecret = getenv('MERCADOPAGO_CLIENT_SECRET') ?: '';
        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('MERCADOPAGO_CLIENT_ID/MERCADOPAGO_CLIENT_SECRET not configured');
        }
        $apiUrl  = rtrim(getenv('MERCADOPAGO_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');
        $token   = $this->getAccessToken($clientId, $clientSecret, $apiUrl);

        $resp = $this->http->post($apiUrl . '/checkout/preferences', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'json'    => [
                'items'              => [[
                    'id'         => $order->order_no,
                    'title'      => 'Game deposit ' . $order->order_no,
                    'quantity'   => 1,
                    'unit_price' => (float) $order->amount,
                ]],
                'external_reference' => $order->order_no,
                'notification_url'   => $siteUrl . '/api/payment/callback?provider=mercadopago',
                'back_urls'          => [
                    'success' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                    'failure' => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                    'pending' => $siteUrl . '/payment/pending?order_no=' . $order->order_no,
                ],
                'auto_return'        => 'approved',
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['init_point']) || empty($data['id'])) {
            throw new \RuntimeException('MercadoPago preference failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['init_point'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        $secret    = getenv('MERCADOPAGO_WEBHOOK_SECRET') ?: '';
        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId    = (string) $request->get('data_id', '');
        if ($secret === '' || !$this->verifySignature($signature, $requestId, $dataId, $secret)) {
            return $failed;
        }

        $data = json_decode($request->rawBody(), true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'payment') {
            return ['valid' => true, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'ignored'];
        }

        $paymentId = (string) ($data['data']['id'] ?? $dataId);
        if ($paymentId === '') {
            return $failed;
        }

        try {
            $payment = $this->fetchPayment($paymentId);
        } catch (\Throwable $e) {
            // 回查失败不确认也不丢弃：返回无效让网关重试
            return $failed;
        }

        return [
            'valid'          => true,
            'order_no'       => (string) ($payment['external_reference'] ?? ''),
            'transaction_id' => (string) ($payment['id'] ?? $paymentId),
            'amount'         => (string) ($payment['transaction_amount'] ?? ''),
            'status'         => match (strtolower((string) ($payment['status'] ?? ''))) {
                'approved' => 'success',
                'rejected', 'cancelled', 'refunded', 'charged_back' => 'failed',
                default    => 'ignored',
            },
        ];
    }

    /**
     * 验签 manifest 模板取自官方 SDK（dx-php WebhookSignatureValidator）：
     * id:{data_id};request-id:{x-request-id};ts:{ts}; —— 注意不含 type 字段。
     */
    public function verifySignature(string $signature, string $requestId, string $dataId, string $secret): bool
    {
        $ts = '';
        $v1 = '';
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($key === 'ts') {
                $ts = $value;
            } elseif ($key === 'v1') {
                $v1 = $value;
            }
        }
        if ($ts === '' || $v1 === '' || !ctype_digit($ts)) {
            return false;
        }
        if (abs(time() - (int) $ts) > self::TS_TOLERANCE) {
            return false;
        }
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
    }

    private function getAccessToken(string $clientId, string $clientSecret, string $apiUrl): string
    {
        $resp = $this->http->post($apiUrl . '/oauth/token', [
            'form_params' => [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'client_credentials',
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('MercadoPago OAuth failed: ' . ($data['message'] ?? 'unknown'));
        }
        return (string) $data['access_token'];
    }

    private function fetchPayment(string $paymentId): array
    {
        $clientId     = getenv('MERCADOPAGO_CLIENT_ID') ?: '';
        $clientSecret = getenv('MERCADOPAGO_CLIENT_SECRET') ?: '';
        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('MERCADOPAGO_CLIENT_ID/MERCADOPAGO_CLIENT_SECRET not configured');
        }
        $apiUrl = rtrim(getenv('MERCADOPAGO_API_URL') ?: self::API_URL, '/');
        $token  = $this->getAccessToken($clientId, $clientSecret, $apiUrl);

        $resp = $this->http->get($apiUrl . '/v1/payments/' . rawurlencode($paymentId), [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        return json_decode((string) $resp->getBody(), true) ?: [];
    }
}
