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
 * GrabPay（东南亚钱包直连）网关：/v1/charge/init 发起 → 跳转 GrabPay 收银 → 回调完成。
 * - 各站点 base URL 不同（SG/MY/TH...），由 GRABPAY_API_BASE 配置，测试注入 MockHandler
 * - 回调验签：x-signature = HMAC-SHA256(secret, 按 key 排序的 "key:value" 拼接) hex
 * - fail-closed：未配置 GRABPAY_SECRET 或验签失败一律拒绝；金额/订单归属由控制器二次比对
 * - 不支持退款（本地钱包渠道），能力表返回 false
 */
class GrabPayGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://partner-api.grab.com';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    private function baseUrl(): string
    {
        return rtrim(getenv('GRABPAY_API_BASE') ?: self::API_BASE, '/');
    }

    private function merchantId(): string
    {
        $id = getenv('GRABPAY_MERCHANT_ID') ?: '';
        if ($id === '') {
            throw new \RuntimeException('GRABPAY_MERCHANT_ID not configured');
        }
        return $id;
    }

    private function secret(): string
    {
        $secret = getenv('GRABPAY_SECRET') ?: '';
        if ($secret === '') {
            throw new \RuntimeException('GRABPAY_SECRET not configured');
        }
        return $secret;
    }

    /**
     * 官方签名算法：payload 的 key 按字典序排序，拼接 "key:value"（数组值取紧凑 JSON），
     * HMAC-SHA256 hex 输出。
     */
    private function sign(array $payload): string
    {
        ksort($payload);
        $str = '';
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $str .= $key . ':' . $value;
        }
        return hash_hmac('sha256', $str, $this->secret());
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');
        $country = (string) ($method->config['country'] ?? 'SG');

        $payload = [
            'partnerTxnID'      => $order->order_no,
            'merchantID'        => $this->merchantId(),
            'currency'          => strtoupper($order->currency),
            'amount'            => $order->amount,
            'country'           => $country,
            'description'       => 'Game deposit ' . $order->order_no,
            'referenceID'       => $order->order_no,
            'channel'           => 'WEB',
            'terminalType'      => 'WEB',
            'paymentExpiryTime' => date('c', strtotime((string) $order->expires_at ?: '+30 minutes')),
            'items'             => [['itemName' => 'Game deposit', 'quantity' => 1, 'amount' => $order->amount]],
            'callbackUrl'       => $siteUrl . '/api/v1/payment/callback?provider=grabpay',
            'redirectUrl'       => $siteUrl . '/payment/success?order_no=' . $order->order_no,
        ];

        $resp = $this->http->post($this->baseUrl() . '/v1/charge/init', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'x-partner-id'  => $this->merchantId(),
                'x-signature'   => $this->sign($payload),
            ],
            'json' => $payload,
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['redirectUrl']) || empty($data['referenceID'])) {
            throw new \RuntimeException('GrabPay charge init failed: ' . ($data['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['redirectUrl'],
            'transaction_id' => (string) $data['referenceID'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        // fail-closed：未配置 secret 时拒绝一切回调
        if ((getenv('GRABPAY_SECRET') ?: '') === '') {
            return $failed;
        }

        $raw  = (string) $request->rawBody();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $failed;
        }

        $expected = $this->sign($data);
        $provided = (string) $request->header('x-signature', '');
        if (!hash_equals($expected, $provided)) {
            return $failed;
        }

        $orderNo = (string) ($data['partnerTxnID'] ?? '');
        $refId   = (string) ($data['referenceID'] ?? '');
        $amount  = (string) ($data['amount'] ?? '');
        $status  = strtoupper((string) ($data['txnStatus'] ?? ''));

        // 中间态（PENDING）无需处理，成功应答防重试
        if ($status === 'PENDING') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $refId, 'amount' => '', 'status' => 'ignored'];
        }
        if ($status !== 'SUCCESS') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $refId, 'amount' => '', 'status' => 'failed'];
        }

        // 成功必须带金额与 referenceID（= 创建时存的 transaction_id），缺任一环节即拒绝
        if ($amount === '' || $refId === '') {
            return $failed;
        }

        return [
            'valid'          => true,
            'order_no'       => $orderNo,
            'transaction_id' => $refId,
            'amount'         => $amount,
            'status'         => 'success',
        ];
    }
}
