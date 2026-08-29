<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use GuzzleHttp\Client;
use support\Request;

/**
 * Paystack（尼日利亚）支付网关：/transaction/initialize 生成托管收银页。
 * 回调验签（x-paystack-signature = HMAC-SHA512(raw body, secret) hex）后，
 * 再以 /transaction/verify 权威回查（status=success + 金额比对）双保险。
 */
class PaystackGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.paystack.co';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $secret = getenv('PAYSTACK_SECRET_KEY') ?: '';
        if (!$secret) {
            throw new \RuntimeException('PAYSTACK_SECRET_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('PAYSTACK_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $email = (string) ($order->user?->email ?? '');
        if ($email === '') {
            throw new \RuntimeException('Paystack requires an email on the user account');
        }

        $resp = $this->http->post($apiUrl . '/transaction/initialize', [
            'headers' => ['Authorization' => 'Bearer ' . $secret],
            'json'    => [
                'email'        => $email,
                'amount'       => $this->toMinor($order->amount),
                'reference'    => $order->order_no,
                'currency'     => $order->currency,
                'callback_url' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['status']) || empty($data['data']['authorization_url']) || empty($data['data']['reference'])) {
            throw new \RuntimeException('Paystack initialize failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['data']['authorization_url'],
            'transaction_id' => (string) $data['data']['reference'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        $secret = getenv('PAYSTACK_SECRET_KEY') ?: '';
        $sig    = (string) $request->header('x-paystack-signature', '');
        if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha512', $request->rawBody(), $secret), $sig)) {
            return $failed;
        }

        $data = json_decode($request->rawBody(), true);
        $event = (string) (is_array($data) ? ($data['event'] ?? '') : '');
        $reference = (string) (is_array($data) ? ($data['data']['reference'] ?? '') : '');
        if ($event === '') {
            return $failed;
        }
        // 非 charge 事件（transfer/invoice/subscription 等）无需处理
        if ($event !== 'charge.success' && $event !== 'charge.failed') {
            return ['valid' => true, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'ignored'];
        }
        if ($reference === '') {
            return $failed;
        }

        try {
            $verified = $this->fetchVerify($reference);
        } catch (\Throwable $e) {
            // 回查失败不确认也不丢弃：返回无效让 Paystack 重试
            return $failed;
        }

        $status = (string) ($verified['status'] ?? '');
        if ($status !== 'success') {
            return ['valid' => true, 'order_no' => $reference, 'transaction_id' => $reference, 'amount' => '', 'status' => 'failed'];
        }

        return [
            'valid'          => true,
            'order_no'       => $reference,
            'transaction_id' => $reference,
            'amount'         => sprintf('%.4f', (int) ($verified['amount'] ?? 0) / 100),
            'status'         => 'success',
        ];
    }

    /** Paystack 金额一律最小单位（kobo/分），转 4 位小数主单位与订单 bccomp 对齐 */
    public function toMinor(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** 权威回查：GET /transaction/verify/:reference，返回 data 部分 */
    protected function fetchVerify(string $reference): array
    {
        $secret = getenv('PAYSTACK_SECRET_KEY') ?: '';
        if (!$secret) {
            throw new \RuntimeException('PAYSTACK_SECRET_KEY not configured');
        }
        $apiUrl = rtrim(getenv('PAYSTACK_API_URL') ?: self::API_URL, '/');
        $resp   = $this->http->get($apiUrl . '/transaction/verify/' . rawurlencode($reference), [
            'headers' => ['Authorization' => 'Bearer ' . $secret],
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        if (!is_array($data) || empty($data['status'])) {
            throw new \RuntimeException('Paystack verify failed');
        }
        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }
}
