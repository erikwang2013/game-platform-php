<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use support\Request;
use support\Response;
use support\Db;

class WithdrawController extends BaseController
{
    /**
     * POST /api/withdraw/apply
     */
    public function apply(Request $request): Response
    {
        // Check global withdraw switch
        $globalSwitch = PlatformConfig::get('withdraw', 'global_switch');
        if (!$globalSwitch) {
            return $this->fail('Withdrawal is currently disabled', 403);
        }

        $validator = validator($request->all(), [
            'platform_amount' => 'required|numeric|min:0.0001',
            'method'          => 'required|in:paypal,bank,crypto',
            'account_info'    => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId         = $request->userId;
        $platformAmount = (string) $request->input('platform_amount');
        $method         = $request->input('method');
        $accountInfo    = $request->input('account_info');

        // Check minimum withdrawal amount
        $minAmount = PlatformConfig::get('withdraw', 'min_amount', '0.0001');
        if (bccomp($platformAmount, $minAmount, 4) < 0) {
            return $this->fail('Amount below minimum withdrawal limit', 400);
        }

        // Check daily withdrawal limit
        $dailyLimit = PlatformConfig::get('withdraw', 'daily_limit', '0');
        if (bccomp($dailyLimit, '0', 4) > 0) {
            $todaySum = WithdrawOrder::where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->whereDate('created_at', date('Y-m-d'))
                ->sum('platform_amount');

            $totalAfter = bcadd((string) $todaySum, $platformAmount, 4);
            if (bccomp($totalAfter, $dailyLimit, 4) > 0) {
                return $this->fail('Daily withdrawal limit exceeded', 400);
            }
        }

        // Check wallet balance
        $wallet = UserWallet::where('user_id', $userId)->first();
        if (!$wallet || bccomp($wallet->balance, $platformAmount, 4) < 0) {
            return $this->fail('Insufficient balance', 400);
        }

        Db::beginTransaction();

        try {
            // Deduct balance
            $deducted = UserWallet::deductBalance($userId, $platformAmount);
            if (!$deducted) {
                Db::rollBack();
                return $this->fail('Failed to deduct balance', 500);
            }

            // Determine auto-approve
            $autoThreshold = PlatformConfig::get('withdraw', 'auto_approve_threshold', '0');
            if (bccomp($autoThreshold, '0', 4) > 0 && bccomp($platformAmount, $autoThreshold, 4) < 0) {
                $status = 'approved';
            } else {
                $status = 'pending';
            }

            // Generate order number: WTH + YmdHis + random 4 digits
            $orderNo = 'WTH' . date('YmdHis') . str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // Create withdraw order
            $order = new WithdrawOrder();
            $order->id              = $this->generateId();
            $order->order_no        = $orderNo;
            $order->user_id         = $userId;
            $order->platform_amount = $platformAmount;
            $order->method          = $method;
            $order->account_info    = $accountInfo;
            $order->status          = $status;
            $order->save();

            // Refresh wallet to get balance after deduction
            $wallet->refresh();
            $balanceAfter = $wallet->balance;

            // Create transaction record
            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $userId;
            $transaction->type          = 'withdraw';
            $transaction->amount        = '-' . $platformAmount;
            $transaction->balance_after = $balanceAfter;
            $transaction->ref_type      = 'withdraw_order';
            $transaction->ref_id        = $order->id;
            $transaction->remark        = "Withdraw via {$method}";
            $transaction->save();

            Db::commit();

            return $this->success([
                'order_id'        => $this->encodeId($order->id),
                'order_no'        => $order->order_no,
                'platform_amount' => $platformAmount,
                'status'          => $status,
                'balance_after'   => $balanceAfter,
                'created_at'      => $order->created_at,
            ], 'Withdrawal request submitted');
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->fail('Withdrawal failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/withdraw/orders
     */
    public function orders(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $paginator = WithdrawOrder::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $order) {
            $items[] = [
                'id'              => $this->encodeId($order->id),
                'order_no'        => $order->order_no,
                'platform_amount' => $order->platform_amount,
                'method'          => $order->method,
                'status'          => $order->status,
                'review_note'     => $order->review_note,
                'created_at'      => $order->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
