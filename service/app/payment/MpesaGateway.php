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
 * M-Pesa（肯尼亚 Safaricom Daraja）STK Push 网关。
 * 无签名机制：安全边界 = CheckoutRequestID 与订单 transaction_id 一致 + ResultCode=0
 * + CallbackMetadata 金额与订单比对（fail-closed），任何一环缺失即拒绝。
 */
class MpesaGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.safaricom.co.ke';

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $key      = getenv('MPESA_CONSUMER_KEY') ?: '';
        $secret   = getenv('MPESA_CONSUMER_SECRET') ?: '';
        $passkey  = getenv('MPESA_PASSKEY') ?: '';
        $shortcode = getenv('MPESA_SHORTCODE') ?: '';
        if (!$key || !$secret || !$passkey || !$shortcode) {
            throw new \RuntimeException('MPESA_CONSUMER_KEY/MPESA_CONSUMER_SECRET/MPESA_PASSKEY/MPESA_SHORTCODE not configured');
        }
        $apiUrl  = rtrim(getenv('MPESA_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $phone = (string) ($order->user?->phone ?? '');
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }
        if (!preg_match('/^254[17]\d{8}$/', $phone)) {
            throw new \RuntimeException('M-Pesa requires a Kenyan phone number on the user account');
        }

        // 官方要求整数 KES：小数金额无法精确推送，直接拒绝而非静默截断
        if ((float) $order->amount !== floor((float) $order->amount)) {
            throw new \RuntimeException('M-Pesa amount must be a whole number in KES');
        }

        $ts        = date('YmdHis');
        $password  = base64_encode($shortcode . $passkey . $ts);
        $token     = $this->getAccessToken($key, $secret, $apiUrl);
        $resp      = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/mpesa/stkpush/v1/processrequest', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'json'    => [
                'BusinessShortCode' => $shortcode,
                'Password'          => $password,
                'Timestamp'         => $ts,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => (int) $order->amount,
                'PartyA'            => $phone,
                'PartyB'            => $shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => $siteUrl . '/api/v1/payment/callback?provider=mpesa',
                // 官方限制 12 字符；仅展示用途，安全匹配靠 CheckoutRequestID
                'AccountReference'  => substr($order->order_no, 0, 12),
                'TransactionDesc'   => substr('Game deposit', 0, 13),
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        // ResponseCode "0" 仅代表 PIN 弹窗已下发，非付款成功；回调才是最终结果
        if (($data['ResponseCode'] ?? '') !== '0' || empty($data['CheckoutRequestID'])) {
            throw new \RuntimeException('M-Pesa STK push failed: ' . ($data['ResponseDescription'] ?? 'unknown'));
        }

        // STK Push 无托管收银页：返回站内等待页，前端提示用户手机确认
        return [
            'checkout_url'   => $siteUrl . '/payment/pending?order_no=' . $order->order_no,
            'transaction_id' => (string) $data['CheckoutRequestID'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $failed = ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];

        // fail-closed：未配置短码（本网关业务标识）时拒绝一切回调
        if ((getenv('MPESA_SHORTCODE') ?: '') === '') {
            return $failed;
        }

        $data = json_decode($request->rawBody(), true);
        $stk  = is_array($data) ? ($data['Body']['stkCallback'] ?? null) : null;
        if (!is_array($stk)) {
            return $failed;
        }

        $checkoutId = (string) ($stk['CheckoutRequestID'] ?? '');
        $resultCode = (int) ($stk['ResultCode'] ?? -1);
        if ($checkoutId === '') {
            return $failed;
        }

        // 无签名：必须能按 CheckoutRequestID 找到本平台订单（创建时已存 transaction_id），否则拒绝
        $order = $this->findOrderByCheckoutRequest($checkoutId);
        if (!$order) {
            return $failed;
        }

        // 失败回调（用户取消/超时等）无 CallbackMetadata：透传 failed 让控制器取消订单
        if ($resultCode !== 0) {
            return ['valid' => true, 'order_no' => $order->order_no, 'transaction_id' => $checkoutId, 'amount' => '', 'status' => 'failed'];
        }

        // 成功必须带确认金额：回调金额与订单的比对由控制器统一完成
        $amount = $this->metadataValue($stk, 'Amount');
        if ($amount === '') {
            return $failed;
        }

        return [
            'valid'          => true,
            'order_no'       => $order->order_no,
            'transaction_id' => $checkoutId,
            'amount'         => $amount,
            'status'         => 'success',
        ];
    }

    protected function findOrderByCheckoutRequest(string $checkoutId): ?DepositOrder
    {
        return DepositOrder::where('transaction_id', $checkoutId)->first();
    }

    protected function getAccessToken(string $key, string $secret, string $apiUrl): string
    {
        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->get($apiUrl . '/oauth/v1/generate', [
            'query'   => ['grant_type' => 'client_credentials'],
            'headers' => ['Authorization' => 'Basic ' . base64_encode($key . ':' . $secret)],
        ]);
        $data = json_decode((string) $resp->getBody(), true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('M-Pesa OAuth failed');
        }
        return (string) $data['access_token'];
    }

    /** CallbackMetadata.Item 是 Name/Value 数组，按名称取值 */
    private function metadataValue(array $stk, string $name): string
    {
        $items = $stk['CallbackMetadata']['Item'] ?? [];
        if (!is_array($items)) {
            return '';
        }
        foreach ($items as $item) {
            if (is_array($item) && ($item['Name'] ?? '') === $name && isset($item['Value'])) {
                return (string) $item['Value'];
            }
        }
        return '';
    }
}
