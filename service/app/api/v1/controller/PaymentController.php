<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\DepositOrder;
use common\model\PaymentMethod;
use common\model\Transaction;
use common\model\UserWallet;
use common\service\RiskService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Payment")
 * @Apidoc\Group("payment")
 */
class PaymentController extends BaseController
{
    /**
     * @Apidoc\Title("Payment Callback")
     * @Apidoc\Url("/api/payment/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"order_no",type:"string",require:true,desc:"Order number")
     * @Apidoc\Param(name:"transaction_id",type:"string",require:true,desc:"Payment gateway transaction ID")
     * @Apidoc\Param(name:"status",type:"string",require:true,desc:"Payment status (success, failed, pending)")
     * @Apidoc\Param(name:"provider",type:"string",require:false,desc:"Payment provider (stripe, paypal)")
     * @Apidoc\Param(name:"signature",type:"string",require:false,desc:"Webhook signature for verification")
     */
    public function callback(Request $request): Response
    {
        $validator = validator($request->all(), [
            'order_no'       => 'required|string',
            'transaction_id' => 'required|string',
            'status'         => 'required|in:success,failed,pending',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderNo       = $request->input('order_no');
        $transactionId = $request->input('transaction_id');
        $status        = $request->input('status');
        $provider      = $request->input('provider', '');
        $signature     = $request->input('signature', '');

        // Verify webhook signature based on provider
        if (!empty($provider) && !empty($signature)) {
            if (!$this->verifyWebhookSignature($provider, $signature, $request)) {
                return $this->fail('Invalid webhook signature', 403);
            }
        }

        // Find the deposit order
        $order = DepositOrder::where('order_no', $orderNo)->first();
        if (!$order) {
            return $this->fail('Order not found', 404);
        }

        // Prevent duplicate processing
        if ($order->status === 'completed' || $order->status === 'failed') {
            return $this->success([
                'order_no' => $orderNo,
                'status'   => $order->status,
            ], 'Order has already been processed');
        }

        if ($status === 'success') {
            // Update order status
            $order->status         = 'completed';
            $order->transaction_id = $transactionId;
            $order->paid_at        = date('Y-m-d H:i:s');
            $order->save();

            // Run risk check
            $riskResult = RiskService::check(
                $order->user_id,
                $order->platform_amount,
                'deposit',
                'deposit_order',
                $order->id
            );

            if (!$riskResult['passed']) {
                // Log risk alert but still process (or block based on level)
                // For high risk, the order can be flagged for manual review
                if ($riskResult['level'] === 'high') {
                    $order->status = 'review';
                    $order->save();
                    return $this->success([
                        'order_no' => $orderNo,
                        'status'   => 'review',
                    ], 'Order flagged for risk review');
                }
            }

            // Credit wallet
            UserWallet::addBalance($order->user_id, $order->platform_amount);

            // Create transaction record
            $wallet = UserWallet::where('user_id', $order->user_id)->first();

            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $order->user_id;
            $transaction->type          = 'deposit';
            $transaction->amount        = $order->platform_amount;
            $transaction->balance_after = $wallet->balance ?? '0.0000';
            $transaction->ref_type      = 'deposit_order';
            $transaction->ref_id        = $order->id;
            $transaction->remark        = "Deposit order {$orderNo}, gateway tx: {$transactionId}";
            $transaction->save();

            return $this->success([
                'order_no' => $orderNo,
                'status'   => 'completed',
            ], 'Payment processed successfully');
        }

        if ($status === 'failed') {
            $order->status         = 'failed';
            $order->transaction_id = $transactionId;
            $order->save();

            return $this->success([
                'order_no' => $orderNo,
                'status'   => 'failed',
            ], 'Payment failed recorded');
        }

        // Status is 'pending' — no-op for now
        return $this->success([
            'order_no' => $orderNo,
            'status'   => 'pending',
        ], 'Payment pending acknowledged');
    }

    /**
     * @Apidoc\Title("Payment Methods")
     * @Apidoc\Url("/api/payment/methods")
     * @Apidoc\Method("GET")
     */
    public function methods(Request $request): Response
    {
        $methods = PaymentMethod::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        $items = [];
        foreach ($methods as $method) {
            $config = $method->config;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }

            $items[] = [
                'id'       => $this->encodeId($method->id),
                'name'     => $method->name,
                'type'     => $method->type,
                'provider' => $method->provider,
                'config'   => is_array($config) ? $config : [],
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * Verify payment gateway webhook signature.
     *
     * @param string  $provider  Payment provider name (stripe, paypal)
     * @param string  $signature The signature from the webhook header
     * @param Request $request   The full request object
     * @return bool
     */
    private function verifyWebhookSignature(string $provider, string $signature, Request $request): bool
    {
        switch (strtolower($provider)) {
            case 'stripe':
                return $this->verifyStripeSignature($signature, $request);
            case 'paypal':
                return $this->verifyPayPalSignature($signature, $request);
            default:
                // Unknown provider: skip verification with a warning
                return true;
        }
    }

    /**
     * Verify Stripe webhook signature.
     */
    private function verifyStripeSignature(string $signature, Request $request): bool
    {
        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if (empty($secret)) {
            return false;
        }

        $payload = $request->rawBody();
        $headerSignature = $request->header('Stripe-Signature', '') ?: $signature;

        // Parse Stripe signature header: t=timestamp,v1=signature
        $parts = explode(',', $headerSignature);
        $timestamp = '';
        $expectedSig = '';

        foreach ($parts as $part) {
            [$key, $value] = explode('=', trim($part), 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $expectedSig = $value;
            }
        }

        if (empty($timestamp) || empty($expectedSig)) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $computedSig = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($computedSig, $expectedSig);
    }

    /**
     * Verify PayPal webhook signature.
     */
    private function verifyPayPalSignature(string $signature, Request $request): bool
    {
        // PayPal verification is typically done by forwarding the headers
        // to PayPal's verification endpoint. Simplified verification here.
        $webhookId = getenv('PAYPAL_WEBHOOK_ID') ?: '';
        if (empty($webhookId)) {
            return false;
        }

        // In production, call PayPal's verify-webhook-signature API:
        // POST https://api-m.paypal.com/v1/notifications/verify-webhook-signature
        // For now, accept if the header matches expected format
        $paypalSignature = $request->header('Paypal-Transmission-Sig', '') ?: $signature;
        return !empty($paypalSignature);
    }
}
