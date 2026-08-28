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
 * Coinbase Commerce 加密支付网关：charge API 生成托管支付页，支持 USDC/BTC/ETH 等主流币。
 */
class CoinbaseCommerceGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.commerce.coinbase.com';

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $apiKey = getenv('COINBASE_COMMERCE_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \RuntimeException('COINBASE_COMMERCE_API_KEY not configured');
        }
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post(self::API_URL . '/charges', [
            'headers' => [
                'X-CC-Api-Key' => $apiKey,
                'X-CC-Version' => '2018-03-22',
            ],
            'json' => [
                'name'         => 'Game deposit ' . $order->order_no,
                'description'  => 'Game deposit ' . $order->order_no,
                'pricing_type' => 'fixed_price',
                'local_price'  => [
                    'amount'   => (string) $order->amount,
                    'currency' => $order->currency,
                ],
                'metadata'     => ['order_no' => $order->order_no],
                'redirect_url' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                'cancel_url'   => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        $charge = $data['data'] ?? [];

        if (empty($charge['id']) || empty($charge['hosted_url'])) {
            throw new \RuntimeException('Coinbase charge failed: ' . ($data['error']['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $charge['hosted_url'],
            'transaction_id' => (string) $charge['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $data = json_decode($request->rawBody(), true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $event  = (string) ($data['event']['type'] ?? '');
        $charge = $data['event']['data'] ?? [];

        $mapped = match ($event) {
            'charge:confirmed' => 'success',
            'charge:failed', 'charge:cancelled', 'charge:expired' => 'failed',
            default            => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($charge['metadata']['order_no'] ?? ''),
            'transaction_id' => (string) ($charge['id'] ?? ''),
            'amount'         => (string) ($charge['pricing']['local']['amount'] ?? ''),
            'status'         => $mapped,
        ];
    }
}
