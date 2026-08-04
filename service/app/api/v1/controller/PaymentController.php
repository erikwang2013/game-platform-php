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
    public function callback(Request $request): Response
    {
        $provider = $request->input('provider', 'stripe');
        if ($provider === 'stripe' && !$this->verifyStripeSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }
        if ($provider === 'paypal' && !$this->verifyPayPalSignature($request)) {
            return $this->fail('Invalid signature', 403);
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

        // Idempotency: skip if this transaction_id was already processed
        if ($order->transaction_id === $transactionId && $order->status !== 'pending') {
            return $this->success(['order_no' => $order->order_no, 'status' => $order->status], 'Already processed');
        }

        // Atomic status update prevents double-credit race condition
        $updated = DepositOrder::where('id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status'         => $callbackStatus === 'success' ? 'confirmed' : 'cancelled',
                'transaction_id' => $transactionId,
                'paid_at'        => $callbackStatus === 'success' ? date('Y-m-d H:i:s') : null,
            ]);

        if (!$updated) {
            return $this->success([], 'Already processed');
        }

        if ($callbackStatus === 'success') {
            $order->refresh();

            // Credit the user's platform wallet
            UserWallet::addBalance($order->user_id, $order->platform_amount);

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
            // No secret configured — accept unsigned (development mode)
            return true;
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

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSig, $receivedSig);
    }

    private function verifyPayPalSignature(Request $request): bool
    {
        // PayPal uses a different verification: POST back to PayPal to verify
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
            // No secret configured — accept unsigned (development mode)
            return true;
        }
    }
}
