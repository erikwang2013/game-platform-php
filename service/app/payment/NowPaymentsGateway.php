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
 * NOWPayments 加密支付网关：invoice API 生成支付页，支持 USDT TRC20/ERC20 等 300+ 币种。
 */
class NowPaymentsGateway implements PaymentGatewayInterface
{
    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $apiKey = getenv('NOWPAYMENTS_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \RuntimeException('NOWPAYMENTS_API_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('NOWPAYMENTS_API_URL') ?: 'https://api.nowpayments.io', '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');
        // 种子里的明文 JSON 会被 Encryptable 宽松解密成字符串，需先解码
        $config  = is_array($method->config) ? $method->config : (json_decode((string) $method->config, true) ?: []);
        $network = strtoupper((string) ($config['network'] ?? 'TRC20'));

        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/v1/invoice', [
            'headers' => ['x-api-key' => $apiKey],
            'json'    => [
                'price_amount'      => (string) $order->amount,
                'price_currency'    => $order->currency,
                'order_id'          => $order->order_no,
                'order_description' => 'Game deposit ' . $order->order_no,
                'pay_currency'      => $network === 'ERC20' ? 'usdt' : 'usdttrc20',
                'ipn_callback_url'  => $siteUrl . '/api/payment/callback?provider=nowpayments',
                'success_url'       => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                'cancel_url'        => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                'valid_until'       => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['id']) || empty($data['payment_url'])) {
            throw new \RuntimeException('NOWPayments invoice failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['payment_url'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $data = json_decode($request->rawBody(), true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $status = (string) ($data['payment_status'] ?? '');
        $mapped = match ($status) {
            'finished'            => 'success',
            'failed', 'expired', 'refunded' => 'failed',
            default               => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($data['order_id'] ?? ''),
            'transaction_id' => (string) ($data['payment_id'] ?? ''),
            'amount'         => (string) ($data['price_amount'] ?? ''),
            'status'         => $mapped,
        ];
    }
}
