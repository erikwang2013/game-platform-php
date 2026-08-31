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
 * Adyen 企业级收单网关（Checkout API v71）。
 * - 托管收银页：POST /sessions 返回 session 托管 URL
 * - 回调验签：webhook HMAC-SHA256（对原始报文整体签名，additionalData.hmacSignature 比对）
 * - 支持退款（Idempotency-Key 幂等）与状态查询 —— 补齐退款/对账能力缺口
 * - 验签在网关内完成（fail-closed：未配置 ADYEN_HMAC_KEY 拒绝一切回调）
 */
class AdyenGateway implements PaymentGatewayInterface, RefundableGatewayInterface, QueryableGatewayInterface
{
    private const API_VERSION = 'v71';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    private function baseUrl(): string
    {
        $live = strtolower(getenv('ADYEN_ENV') ?: 'test') === 'live';
        return $live
            ? 'https://checkout-live.adyen.com/' . self::API_VERSION
            : 'https://checkout-test.adyen.com/' . self::API_VERSION;
    }

    private function apiKey(): string
    {
        $key = getenv('ADYEN_API_KEY') ?: '';
        if ($key === '') {
            throw new \RuntimeException('ADYEN_API_KEY not configured');
        }
        return $key;
    }

    private function merchantAccount(): string
    {
        $account = getenv('ADYEN_MERCHANT_ACCOUNT') ?: '';
        if ($account === '') {
            throw new \RuntimeException('ADYEN_MERCHANT_ACCOUNT not configured');
        }
        return $account;
    }

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = $this->http->post($this->baseUrl() . '/sessions', [
            'headers' => ['X-API-Key' => $this->apiKey()],
            'json'    => [
                'merchantAccount' => $this->merchantAccount(),
                'reference'       => $order->order_no,
                'amount'          => [
                    'currency' => strtoupper($order->currency),
                    'value'    => (int) CurrencyUtils::toMinor($order->amount, $order->currency),
                ],
                'returnUrl' => $siteUrl . '/payment/result?order_no=' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['id']) || empty($data['url'])) {
            throw new \RuntimeException('Adyen session failed: ' . ($data['errorCode'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['url'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        // fail-closed：未配置 HMAC key 时拒绝一切回调
        $hmacKey = getenv('ADYEN_HMAC_KEY') ?: '';
        if ($hmacKey === '') {
            return $failed;
        }

        $raw  = (string) $request->rawBody();
        $data = json_decode($raw, true);
        $item = is_array($data) ? ($data['notificationItems'][0]['NotificationRequestItem'] ?? null) : null;
        if (!is_array($item)) {
            return $failed;
        }

        // Adyen 官方验签：hmacSignature 字段本身不参与签名计算（签名覆盖它会导致自指无法生成）。
        // 与官方 PHP SDK calculateHMAC 一致：解码后摘除 additionalData.hmacSignature，
        // 对规范化 JSON（默认 json_encode，键序=报文序）计算 HMAC-SHA256 base64 后比对。
        $provided = (string) ($item['additionalData']['hmacSignature'] ?? '');
        $signItem = $item;
        unset($signItem['additionalData']['hmacSignature']);
        $expected = base64_encode(hash_hmac('sha256', json_encode($signItem), $hmacKey, true));
        if (!hash_equals($expected, $provided)) {
            return $failed;
        }

        // 商户账号必须匹配本平台配置：防跨商户 webhook 冒用
        if ((string) ($item['merchantAccount'] ?? '') !== $this->merchantAccount()) {
            return $failed;
        }

        $orderNo = (string) ($item['reference'] ?? '');
        $pspRef  = (string) ($item['pspReference'] ?? '');

        // 只有授权事件才可能入账；退款/取消/对账单等其他事件按 ignored 应答防重试
        if (($item['eventCode'] ?? '') !== 'AUTHORISATION') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $pspRef, 'amount' => '', 'status' => 'ignored'];
        }
        if (($item['success'] ?? '') !== 'true') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $pspRef, 'amount' => '', 'status' => 'failed'];
        }

        $amount = (string) ($item['amount']['value'] ?? '');
        $currency = (string) ($item['amount']['currency'] ?? '');

        return [
            'valid'          => true,
            'order_no'       => $orderNo,
            'transaction_id' => $pspRef,
            'amount'         => $amount === '' ? '' : CurrencyUtils::fromMinor($amount, $currency),
            'status'         => 'success',
        ];
    }

    public function refund(DepositOrder $order, string $amount, string $reason = ''): array
    {
        // 幂等：Idempotency-Key 按订单号生成，同一订单重复调用返回同一退款结果
        $resp = $this->http->post($this->baseUrl() . "/payments/{$order->transaction_id}/refunds", [
            'headers' => [
                'X-API-Key'       => $this->apiKey(),
                'Idempotency-Key' => 'refund-' . $order->order_no,
            ],
            'json' => [
                'merchantAccount' => $this->merchantAccount(),
                'amount'          => [
                    'currency' => strtoupper($order->currency),
                    'value'    => (int) CurrencyUtils::toMinor($amount, $order->currency),
                ],
                'reference' => 'refund-' . $order->order_no,
                'reason'    => $reason,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        // 退款异步受理：拿到 pspReference 即受理成功；REFUSED 才是拒绝
        if (empty($data['pspReference']) || ($data['status'] ?? '') === 'refused') {
            throw new \RuntimeException('Adyen refund refused: ' . ($data['refusalReason'] ?? 'unknown'));
        }

        return ['success' => true, 'refund_id' => (string) $data['pspReference']];
    }

    public function query(DepositOrder $order): array
    {
        $resp = $this->http->get($this->baseUrl() . "/payments/{$order->transaction_id}", [
            'headers' => ['X-API-Key' => $this->apiKey()],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        $status = match (strtolower((string) ($data['resultCode'] ?? ''))) {
            'authorised', 'received' => 'confirmed',
            'refused', 'cancelled', 'expired', 'error' => 'failed',
            default => 'pending',
        };

        $value = (string) ($data['amount']['value'] ?? '');
        $currency = (string) ($data['amount']['currency'] ?? '');

        return [
            'status' => $status,
            'amount' => $value === '' ? '' : CurrencyUtils::fromMinor($value, $currency),
            'raw'    => $data,
        ];
    }
}
