<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use app\model\Transaction;
use common\service\DepositLogService;
use app\model\UserWallet;
use app\service\RiskService;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Log;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("支付管理")
 * @Apidoc\Group("payment")
 */
class PaymentController extends BaseController
{
    /**
     * @Apidoc\Title("支付回调")
     * @Apidoc\Url("/api/payment/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="order_no", type="string", require=true, desc="订单号")
     * @Apidoc\Param(name="transaction_id", type="string", require=true, desc="交易ID")
     * @Apidoc\Param(name="status", type="string", require=true, desc="支付状态(success/failed)")
     */
    private const ALLOWED_PROVIDERS = ['stripe', 'paypal'];

    public function callback(Request $request): Response
    {
        $provider = strtolower((string) $request->input('provider', ''));
        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            return $this->fail('Invalid provider', 403);
        }
        if ($provider === 'stripe' && !$this->verifyStripeSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }
        if ($provider === 'paypal' && !$this->verifyPayPalSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }

        // 配置了可信回调来源 IP 列表时校验来源，未配置则不强制（仅限服务端到服务端的 Webhook）
        $trustedIps = getenv('CALLBACK_TRUSTED_IPS') ?: '';
        if ($trustedIps !== '') {
            $ips = array_map('trim', explode(',', $trustedIps));
            if (!in_array((string) $request->getRealIp(), $ips, true)) {
                return $this->fail('Source IP not allowed', 403);
            }
        }

        $validator = validator($request->all(), [
            'order_no'       => 'required|string',
            'transaction_id' => 'required|string',
            'status'         => 'required|in:success,failed',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderNo        = $request->input('order_no');
        $transactionId  = $request->input('transaction_id');
        $callbackStatus = $request->input('status');

        $order = DepositOrder::where('order_no', $orderNo)->first();

        if (!$order) {
            return $this->fail('Order not found', 404);
        }

        // 回调 provider 必须与订单支付方式一致，防止跨渠道冒用；支付方式不存在同样拒绝
        $method = PaymentMethod::find($order->payment_method_id);
        if (!$method || strtolower((string) $method->provider) !== $provider) {
            return $this->fail('Provider mismatch', 403);
        }

        // 入账金额与订单核对：回调携带金额时不一致（或非数字）直接拒绝
        $callbackAmount = (string) $request->input('amount', '');
        if ($callbackAmount !== '' && (!is_numeric($callbackAmount) || bccomp($callbackAmount, $order->amount, 4) !== 0)) {
            return $this->fail('Amount mismatch', 422);
        }

        // Idempotency: skip if this transaction_id was already processed
        if ($order->transaction_id === $transactionId && $order->status !== 'pending') {
            return $this->success(['order_no' => $order->order_no, 'status' => $order->status], 'Already processed');
        }

        // 状态更新 + 余额入账 + 流水写入必须同事务，任一步失败整体回滚，防止半入账
        Db::beginTransaction();

        try {
            // Atomic status update prevents double-credit race condition
            $updated = DepositOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status'         => $callbackStatus === 'success' ? 'confirmed' : 'cancelled',
                    'transaction_id' => $transactionId,
                    'paid_at'        => $callbackStatus === 'success' ? date('Y-m-d H:i:s') : null,
                ]);

            if (!$updated) {
                Db::rollBack();
                return $this->success([], 'Already processed');
            }

            if ($callbackStatus === 'success') {
                $order->refresh();

                // Credit the user's platform wallet; failure aborts the transaction
                if (!UserWallet::addBalance($order->user_id, $order->platform_amount)) {
                    throw new \RuntimeException('Wallet credit failed');
                }

                // Refresh wallet to get balance after credit
                $wallet = UserWallet::where('user_id', $order->user_id)->first();
                $balanceAfter = $wallet ? $wallet->balance : '0';

                // Create transaction record
                $transaction = new Transaction();
                $transaction->id            = $this->generateId();
                $transaction->user_id       = $order->user_id;
                $transaction->type          = 'deposit';
                $transaction->amount        = $order->platform_amount;
                $transaction->balance_after = $balanceAfter;
                $transaction->ref_type      = 'deposit';
                $transaction->ref_id        = $order->id;
                $transaction->remark        = "Deposit callback: {$order->order_no}";
                $transaction->save();

                DepositLogService::log($order->id, $order->user_id, $order->amount, $order->currency, 'confirmed');
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            Log::error('Payment callback failed', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
            return $this->fail('Callback processing failed', 500);
        }

        if ($callbackStatus === 'success') {
            // Run risk check
            $riskResult = RiskService::check(
                $order->user_id,
                'deposit',
                [
                    'amount' => $order->platform_amount,
                    'ip'     => $request->getRealIp(),
                ]
            );

            if ($riskResult['result'] === 'block') {
                // MVP: log warning but do NOT reverse the credit
                // Production should queue for manual review
                Log::warning('Deposit credited despite risk block', ['order_no' => $order->order_no, 'user_id' => $order->user_id]);
            }

            return $this->success([
                'order_no' => $order->order_no,
                'status'   => 'confirmed',
                'paid_at'  => $order->paid_at,
            ], 'Deposit confirmed');
        }

        // Callback status is 'failed': order already cancelled atomically above
        return $this->success([
            'order_no' => $order->order_no,
            'status'   => 'cancelled',
        ], 'Deposit cancelled');
    }

    /**
     * @Apidoc\Title("支付方式列表")
     * @Apidoc\Url("/api/payment/methods")
     * @Apidoc\Method("GET")
     */
    public function methods(Request $request): Response
    {
        $methods = PaymentMethod::where('status', 1)
            ->orderBy('sort')
            ->get()
            ->map(function ($method) {
                return [
                    'id'       => $this->encodeId($method->id),
                    'name'     => $method->name,
                    'type'     => $method->type,
                    'provider' => $method->provider,
                ];
            });

        return $this->success(['list' => $methods->toArray()]);
    }

    /**
     * Verify Stripe webhook signature.
     */
    private function verifyStripeSignature(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature', '');
        if (!$signature) {
            return false;
        }

        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if (!$secret) {
            // Fail closed: 未配置密钥时拒绝一切回调，与 JWT 一致
            return false;
        }

        $payload = $request->rawBody();
        $sigParts = explode(',', $signature);
        $timestamp = '';
        $receivedSig = '';

        foreach ($sigParts as $part) {
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            }
            if (str_starts_with($part, 'v1=')) {
                $receivedSig = substr($part, 3);
            }
        }

        if (!$timestamp || !$receivedSig) {
            return false;
        }

        // 时间戳新鲜度：签名 header 的 t= 即 payload 时间戳，缺失时上面已拒绝；
        // 超过 ±5 分钟视为重放
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSig, $receivedSig);
    }

    private function verifyPayPalSignature(Request $request): bool
    {
        // PayPal uses a different verification: POST back to PayPal to verify
        // Fail closed: 未配置 webhook_id 时拒绝一切回调
        if (empty(getenv('PAYPAL_WEBHOOK_ID'))) {
            return false;
        }

        $verifyUrl = getenv('PAYPAL_VERIFY_URL') ?: 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

        try {
            $http = new \GuzzleHttp\Client(['timeout' => 10]);
            $authSig = $request->header('PAYPAL-AUTH-ALGO', '');
            $certUrl = $request->header('PAYPAL-CERT-URL', '');
            $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID', '');
            $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG', '');
            $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME', '');

            $resp = $http->post($verifyUrl, [
                'json' => [
                    'auth_algo' => $authSig,
                    'cert_url' => $certUrl,
                    'transmission_id' => $transmissionId,
                    'transmission_sig' => $transmissionSig,
                    'transmission_time' => $transmissionTime,
                    'webhook_id' => getenv('PAYPAL_WEBHOOK_ID') ?: '',
                    'webhook_event' => json_decode($request->rawBody(), true),
                ],
            ]);
            $result = json_decode((string)$resp->getBody(), true);
            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            // Fail closed: 验证异常视为验签失败
            return false;
        }
    }
}
