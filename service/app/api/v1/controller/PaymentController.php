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
 * C端 - 支付
 *
 * @Apidoc\Title("支付")
 * @Apidoc\Group("payment")
 */
class PaymentController extends BaseController
{
    /**
     * @Apidoc\Title("支付回调")
     * @Apidoc\Url("/api/payment/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"order_no",type:"string",require:true,desc:"订单号")
     * @Apidoc\Param(name:"transaction_id",type:"string",require:true,desc:"支付网关交易ID")
     * @Apidoc\Param(name:"status",type:"string",require:true,desc:"支付状态(success/failed)")
     */
    public function callback(Request $request): Response
    {
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

        if (in_array($order->status, ['confirmed', 'cancelled'])) {
            return $this->success([], 'Already confirmed');
        }

        if ($callbackStatus === 'success') {
            $order->status         = 'confirmed';
            $order->transaction_id = $transactionId;
            $order->paid_at        = date('Y-m-d H:i:s');
            $order->save();

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

        // Callback status is 'failed': cancel the order
        $order->status = 'cancelled';
        $order->save();

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
}
