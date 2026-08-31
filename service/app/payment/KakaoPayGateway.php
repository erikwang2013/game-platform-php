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
 * KakaoPay 韩国支付网关：ready/approve 两步流程，无 Webhook。
 * 用户付款后前端携带 pg_token 回传（扁平参数协议），适配器调用 approve API 并以返回的
 * amount.total 与服务端订单金额比对作为安全边界（无签名机制）。
 */
class KakaoPayGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://kapi.kakao.com';

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $adminKey = getenv('KAKAOPAY_ADMIN_KEY') ?: '';
        $cid      = getenv('KAKAOPAY_CID') ?: '';
        if (!$adminKey || !$cid) {
            throw new \RuntimeException('KAKAOPAY_ADMIN_KEY/KAKAOPAY_CID not configured');
        }
        $apiUrl       = rtrim(getenv('KAKAOPAY_API_URL') ?: self::API_URL, '/');
        $siteUrl      = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');
        $approvalBase = getenv('KAKAOPAY_APPROVAL_URL') ?: $siteUrl . '/payment/success';

        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/v1/payment/ready', [
            'headers' => ['Authorization' => 'KakaoAK ' . $adminKey],
            'json'    => [
                'cid'              => $cid,
                'partner_order_id' => $order->order_no,
                'partner_user_id'  => (string) $order->user_id,
                'item_name'        => 'Game deposit ' . $order->order_no,
                'quantity'         => 1,
                'total_amount'     => (int) $order->amount,
                'tax_free_amount'  => 0,
                'approval_url'     => $approvalBase . '?order_no=' . $order->order_no . '&transaction_id=',
                'cancel_url'       => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
                'fail_url'         => $siteUrl . '/payment/fail?order_no=' . $order->order_no,
            ],
        ]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['tid']) || empty($data['next_redirect_pc_url'])) {
            throw new \RuntimeException('KakaoPay ready failed: ' . ($data['msg'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['next_redirect_pc_url'],
            'transaction_id' => (string) $data['tid'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $orderNo = (string) $request->input('order_no', '');
        $tid     = (string) $request->input('transaction_id', '');
        $status  = (string) $request->input('status', '');
        $pgToken = (string) $request->input('pg_token', '');
        if ($orderNo === '' || $tid === '' || !in_array($status, ['success', 'failed'], true)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }
        // 前端明确上报失败时直接透传，无需调 approve
        if ($status === 'failed') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $tid, 'amount' => '', 'status' => 'failed'];
        }
        if ($pgToken === '') {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $order = $this->findOrder($orderNo);
        if (!$order) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }
        // 幂等：同一 tid 已处理过则不再调 approve（KakaoPay 重复 approve 会报错）
        if ($order->transaction_id === $tid && $order->status !== 'pending') {
            return ['valid' => true, 'order_no' => $orderNo, 'transaction_id' => $tid, 'amount' => '', 'status' => $order->status === 'confirmed' ? 'success' : 'failed'];
        }

        $adminKey = getenv('KAKAOPAY_ADMIN_KEY') ?: '';
        $cid      = getenv('KAKAOPAY_CID') ?: '';
        if (!$adminKey || !$cid) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        try {
            $approve = $this->callApprove([
                'cid'              => $cid,
                'tid'              => $tid,
                'partner_order_id' => $orderNo,
                'partner_user_id'  => (string) $order->user_id,
                'pg_token'         => $pgToken,
            ]);
        } catch (\Throwable $e) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        // 无签名机制：approve 返回的金额与订单金额比对即安全边界，不一致拒绝
        $amountPart = is_array($approve['amount'] ?? null) ? $approve['amount'] : [];
        $total      = (string) ($amountPart['total'] ?? '');
        if (!is_numeric($total) || bccomp($total, $order->amount, 4) !== 0) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        return [
            'valid'          => true,
            'order_no'       => $orderNo,
            'transaction_id' => (string) ($approve['tid'] ?? $tid),
            'amount'         => $total,
            'status'         => 'success',
        ];
    }

    protected function findOrder(string $orderNo): ?DepositOrder
    {
        return DepositOrder::where('order_no', $orderNo)->first();
    }

    protected function callApprove(array $params): array
    {
        $adminKey = getenv('KAKAOPAY_ADMIN_KEY') ?: '';
        $apiUrl   = rtrim(getenv('KAKAOPAY_API_URL') ?: self::API_URL, '/');
        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post($apiUrl . '/v1/payment/approve', [
            'headers' => ['Authorization' => 'KakaoAK ' . $adminKey],
            'json'    => $params,
        ]);
        return json_decode((string) $resp->getBody(), true) ?: [];
    }
}
