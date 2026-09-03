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
 * AstroPay 支付网关：Direct API order/create 生成跳转支付页；回调 MD5 验签后按状态入账。
 */
class AstroPayGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.astropaycard.com';

    /**
     * 回调验签拼接字段（顺序敏感）：md5(order_id.amount.status.secret)。
     * ponytail: 旧版 Direct API 文档已下线，模板无法从公开文档复核；若商户实际模板不同，改这里即可。
     */
    private const SIGNATURE_FIELDS = ['order_id', 'amount', 'status'];

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $login  = getenv('ASTROPAY_LOGIN') ?: '';
        $apiKey = getenv('ASTROPAY_API_KEY') ?: '';
        if (!$login || !$apiKey) {
            throw new \RuntimeException('ASTROPAY_LOGIN/ASTROPAY_API_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('ASTROPAY_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');
        // 种子里的明文 JSON 会被 Encryptable 宽松解密成字符串，需先解码
        $config  = is_array($method->config) ? $method->config : (json_decode((string) $method->config, true) ?: []);
        $country = strtoupper((string) ($config['country'] ?? 'BR'));

        $resp = $this->http->post($apiUrl . '/api/v1/order/create', [
            'json' => [
                'login'        => $login,
                'api_key'      => $apiKey,
                'order_id'     => $order->order_no,
                'amount'       => (string) $order->amount,
                'currency'     => $order->currency,
                'country'      => $country,
                'description'  => 'Game deposit ' . $order->order_no,
                'url_callback' => $siteUrl . '/api/v1/payment/callback?provider=astropay',
            ],
        ]);
        $data     = json_decode((string) $resp->getBody(), true) ?: [];
        $response = $data['response'] ?? [];

        if (empty($response['url']) || empty($response['order_id'])) {
            throw new \RuntimeException('AstroPay order create failed: ' . ($response['error'] ?? $response['error_code'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $response['url'],
            'transaction_id' => (string) $response['order_id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        $body = $request->rawBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            parse_str($body, $data);
        }

        $secret = getenv('ASTROPAY_SECRET') ?: '';
        $sig    = (string) ($data['signature'] ?? '');
        if ($secret === '' || $sig === '' || !$this->verifySignature($data, $sig, $secret)) {
            return $failed;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        return [
            'valid'          => true,
            'order_no'       => (string) ($data['order_id'] ?? ''),
            'transaction_id' => (string) ($data['payment_id'] ?? $data['order_id'] ?? ''),
            'amount'         => (string) ($data['amount'] ?? ''),
            'status'         => match ($status) {
                'success', 'approved' => 'success',
                'failure', 'failed', 'declined', 'cancelled' => 'failed',
                default               => 'ignored',
            },
        ];
    }

    public function verifySignature(array $data, string $signature, string $secret): bool
    {
        $parts = '';
        foreach (self::SIGNATURE_FIELDS as $field) {
            $parts .= (string) ($data[$field] ?? '');
        }
        return hash_equals(md5($parts . $secret), $signature);
    }
}
