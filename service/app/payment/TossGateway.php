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
 * Toss Payments 韩国支付网关：服务端创建支付窗（POST /v1/payments → checkout.url），
 * 认证成功后浏览器带 paymentKey 回跳，服务端 confirm（POST /v1/payments/confirm）收尾。
 * 支付类 webhook（PAYMENT_STATUS_CHANGED）无签名（官方仅 payout/seller 事件带
 * tosspayments-webhook-signature），安全边界 = 携带 secret key 的服务端回查 + 金额比对。
 */
class TossGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.tosspayments.com';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $secretKey = getenv('TOSS_SECRET_KEY') ?: '';
        if ($secretKey === '') {
            throw new \RuntimeException('TOSS_SECRET_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('TOSS_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = $this->http->post($apiUrl . '/v1/payments', [
            'headers' => ['Authorization' => 'Basic ' . base64_encode($secretKey . ':')],
            'json'    => [
                'method'      => 'CARD',
                'amount'      => (int) $order->amount,
                'currency'    => 'KRW',
                'orderId'     => $order->order_no,
                'orderName'   => 'Game deposit ' . $order->order_no,
                'successUrl'  => $siteUrl . '/api/v1/payment/callback?provider=toss',
                'failUrl'     => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (!is_array($data) || empty($data['checkout']['url'])) {
            throw new \RuntimeException('Toss create payment failed: ' . (is_array($data) ? ($data['message'] ?? 'unknown') : 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['checkout']['url'],
            'transaction_id' => (string) ($data['paymentKey'] ?: $order->order_no),
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        if ((getenv('TOSS_SECRET_KEY') ?: '') === '') {
            return $failed;
        }

        $eventType = (string) $request->input('eventType', '');
        return $eventType !== '' ? $this->verifyWebhook($eventType, $request) : $this->verifyRedirect($request);
    }

    private function verifyRedirect(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        $orderNo    = (string) $request->input('order_no', (string) $request->input('orderId', ''));
        $paymentKey = (string) $request->input('paymentKey', '');
        $status     = (string) $request->input('status', '');
        if ($status === 'failed') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $paymentKey, 'amount' => '', 'status' => 'failed'];
        }
        if ($orderNo === '' || $paymentKey === '') {
            return $failed;
        }

        $order = $this->findOrder($orderNo);
        if (!$order) {
            return $failed;
        }
        // 幂等：订单已处理则不再 confirm（Idempotency-Key 兜底重复请求）
        if ($order->status !== 'pending') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $paymentKey, 'amount' => '', 'status' => $order->status === 'confirmed' ? 'success' : 'failed'];
        }
        // 回调携带的认证金额必须与订单金额一致，防客户端篡改 requestPayment amount
        $queryAmount = (string) $request->input('amount', '');
        if (!is_numeric($queryAmount) || bccomp($queryAmount, $order->amount, 4) !== 0) {
            return $failed;
        }

        try {
            $payment = $this->confirm($paymentKey, $order->order_no, $order->amount);
        } catch (\Throwable $e) {
            // confirm 失败不确认也不吞错：拒绝并让网关/前端重试
            return $failed;
        }
        if (!is_array($payment) || empty($payment['paymentKey'])) {
            return $failed;
        }

        return $this->mapPayment($payment, $orderNo);
    }

    private function verifyWebhook(string $eventType, Request $request): array
    {
        if ($eventType !== 'PAYMENT_STATUS_CHANGED') {
            return ['valid' => true, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'ignored'];
        }

        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        $data   = $request->input('data', []);
        $paymentKey = (string) (is_array($data) ? ($data['paymentKey'] ?? '') : '');
        if ($paymentKey === '') {
            return $failed;
        }

        try {
            $payment = $this->fetchPayment($paymentKey);
        } catch (\Throwable $e) {
            // 回查失败不确认也不丢弃：返回无效让网关重试
            return $failed;
        }
        if (!is_array($payment)) {
            return $failed;
        }

        $orderNo = (string) ($payment['orderId'] ?? '');
        $order   = $this->findOrder($orderNo);
        if (!$order) {
            return $failed;
        }
        if ($order->status !== 'pending') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => (string) $paymentKey, 'amount' => '', 'status' => $order->status === 'confirmed' ? 'success' : 'failed'];
        }
        $totalAmount = (string) ($payment['totalAmount'] ?? '');
        if (!is_numeric($totalAmount) || bccomp($totalAmount, $order->amount, 4) !== 0) {
            return $failed;
        }

        return $this->mapPayment($payment, $orderNo);
    }

    private function mapPayment(array $payment, string $fallbackOrderNo): array
    {
        $paymentKey = (string) ($payment['paymentKey'] ?? '');
        return [
            'valid'          => true,
            'order_no'       => (string) ($payment['orderId'] ?? $fallbackOrderNo),
            'transaction_id' => $paymentKey !== '' ? $paymentKey : $fallbackOrderNo,
            'amount'         => (string) ($payment['totalAmount'] ?? ''),
            'status'         => match ((string) ($payment['status'] ?? '')) {
                'DONE' => 'success',
                'ABORTED', 'EXPIRED', 'CANCELED', 'PARTIAL_CANCELED' => 'failed',
                default => 'ignored',
            },
        ];
    }

    protected function findOrder(string $orderNo): ?DepositOrder
    {
        return DepositOrder::where('order_no', $orderNo)->first();
    }

    protected function confirm(string $paymentKey, string $orderId, string $amount): array
    {
        $secretKey = getenv('TOSS_SECRET_KEY') ?: '';
        $apiUrl    = rtrim(getenv('TOSS_API_URL') ?: self::API_URL, '/');
        $resp = $this->http->post($apiUrl . '/v1/payments/confirm', [
            'headers' => [
                'Authorization'   => 'Basic ' . base64_encode($secretKey . ':'),
                'Idempotency-Key' => $orderId,
            ],
            'json' => [
                'paymentKey' => $paymentKey,
                'orderId'    => $orderId,
                'amount'     => (int) $amount,
            ],
        ]);
        return json_decode((string) $resp->getBody(), true) ?: [];
    }

    protected function fetchPayment(string $paymentKey): array
    {
        $secretKey = getenv('TOSS_SECRET_KEY') ?: '';
        $apiUrl    = rtrim(getenv('TOSS_API_URL') ?: self::API_URL, '/');
        $resp = $this->http->get($apiUrl . '/v1/payments/' . rawurlencode($paymentKey), [
            'headers' => ['Authorization' => 'Basic ' . base64_encode($secretKey . ':')],
        ]);
        return json_decode((string) $resp->getBody(), true) ?: [];
    }
}
