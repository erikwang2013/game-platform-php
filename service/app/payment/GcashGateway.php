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
 * GCash 菲律宾钱包网关（经 PayMongo）：sources 流程直接返回 redirect.checkout_url 供 Web 客户端跳转
 * （无需客户端 JS，比 payment_intents 流程简单）。
 * Webhook 验签：Paymongo-Signature: t=...,te=...,li=...，HMAC-SHA256(t . 原始报文)，hex。
 */
class GcashGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.paymongo.com/v1';

    /** 签名时间戳允许偏差（秒） */
    private const TS_TOLERANCE = 300;

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $apiKey = getenv('PAYMONGO_API_KEY') ?: '';
        if (!$apiKey) {
            throw new \RuntimeException('PAYMONGO_API_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('PAYMONGO_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $resp = (new \GuzzleHttp\Client(['timeout' => 15, 'auth' => [$apiKey, '']]))->post($apiUrl . '/sources', [
            'json' => [
                'type'     => 'gcash',
                'amount'   => (int) $this->toMinor($order->amount),
                'currency' => 'PHP',
                'redirect' => [
                    'success' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
                    'failed'  => $siteUrl . '/payment/fail?order_no=' . $order->order_no,
                ],
                'metadata' => ['order_no' => $order->order_no],
            ],
        ]);
        $data   = json_decode((string) $resp->getBody(), true);
        $source = is_array($data['data'] ?? null) ? $data['data'] : [];
        $redirect = is_array($source['redirect'] ?? null) ? $source['redirect'] : [];

        $checkoutUrl = (string) ($redirect['checkout_url'] ?? '');
        if ($checkoutUrl === '' || empty($source['id'])) {
            $errors = $data['errors'] ?? [];
            $msg    = is_array($errors) ? (string) ($errors[0]['detail'] ?? 'unknown') : 'unknown';
            throw new \RuntimeException('PayMongo source create failed: ' . $msg);
        }

        return [
            'checkout_url'   => $checkoutUrl,
            'transaction_id' => (string) $source['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        if (!$this->verifySignature($request)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $data = json_decode($request->rawBody(), true);
        if (!is_array($data)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $dataPart = is_array($data['data'] ?? null) ? $data['data'] : [];
        $attrs    = is_array($dataPart['attributes'] ?? null) ? $dataPart['attributes'] : [];
        $event    = (string) ($attrs['type'] ?? '');
        $mapped   = match ($event) {
            'payment_intent.succeeded', 'payment.succeeded', 'payment.paid' => 'success',
            'payment.failed' => 'failed',
            default          => 'ignored',
        };

        $obj      = is_array($attrs['data'] ?? null) ? $attrs['data'] : [];
        $objAttrs = is_array($obj['attributes'] ?? null) ? $obj['attributes'] : [];
        return [
            'valid'          => true,
            'order_no'       => (string) ($objAttrs['metadata']['order_no'] ?? ''),
            'transaction_id' => (string) ($obj['id'] ?? ''),
            'amount'         => $this->fromMinor((string) ($objAttrs['amount'] ?? '0'), (string) ($objAttrs['currency'] ?? 'PHP')),
            'status'         => $mapped,
        ];
    }

    /** PHP 为 2 位小数币种：金额转分 */
    private function toMinor(string $amount): string
    {
        return bcmul($amount, '100', 0);
    }

    /** 分转回金额字符串 */
    private function fromMinor(string $amount, string $currency): string
    {
        return bcdiv($amount, '100', 4);
    }

    /**
     * 验签：复合格式签名串 = t . '.' . 原始报文，HMAC-SHA256，hex 对比；
     * 兼容旧版纯 hex 报文（HMAC-SHA256(原始报文)）。te 为测试模式、li 为生产模式签名。
     */
    private function verifySignature(Request $request): bool
    {
        $secret    = getenv('PAYMONGO_WEBHOOK_SECRET') ?: '';
        $signature = $request->header('Paymongo-Signature', '') ?: $request->header('X-Paymongo-Signature', '');
        if (!$secret || !$signature) {
            return false;
        }
        $body = $request->rawBody();

        if (str_contains($signature, 't=')) {
            $parts = [];
            foreach (explode(',', $signature) as $kv) {
                [$key, $value] = array_pad(explode('=', $kv, 2), 2, '');
                $parts[$key] = $value;
            }
            $ts  = (string) ($parts['t'] ?? '');
            $sig = ($parts['li'] ?? '') !== '' ? (string) $parts['li'] : (string) ($parts['te'] ?? '');
            if ($ts === '' || $sig === '' || abs(time() - (int) $ts) > self::TS_TOLERANCE) {
                return false;
            }
            $expected = hash_hmac('sha256', $ts . '.' . $body, $secret);
            return hash_equals($expected, $sig);
        }

        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $signature);
    }
}
