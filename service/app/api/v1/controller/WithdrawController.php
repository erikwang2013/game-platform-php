<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserIdentity;
use common\model\UserWallet;
use common\model\WithdrawLimit;
use common\model\WithdrawOrder;
use support\Db;
use support\Request;
use support\Response;

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

        // Check KYC-based tiered limits
        $level    = 'default';
        $identity = UserIdentity::where('user_id', $request->userId)->first();
        if ($identity && $identity->status === 'approved') {
            $level = 'verified';
        }
        // VIP check could go here later

        $limit = WithdrawLimit::getByLevel($level);
        if ($limit) {
            // Override platform config with tiered limits
            $minAmount    = $limit->single_min;
            $maxAmount    = $limit->single_max;
            $dailyLimit   = $limit->daily_limit;
            $monthlyLimit = $limit->monthly_limit;
            $autoThreshold = $limit->auto_approve_threshold;
            $feePct       = $limit->fee_pct;
            $feeMax       = $limit->fee_max;
        } else {
            // Fallback to PlatformConfig when no tiered limit exists
            $minAmount    = PlatformConfig::get('withdraw', 'min_amount', '0.0001');
            $maxAmount    = '0';
            $dailyLimit   = PlatformConfig::get('withdraw', 'daily_limit', '0');
            $monthlyLimit = '0';
            $autoThreshold = PlatformConfig::get('withdraw', 'auto_approve_threshold', '0');
            $feePct       = '0';
            $feeMax       = '0';
        }

        // Check minimum withdrawal amount
        if (bccomp($platformAmount, $minAmount, 4) < 0) {
            return $this->fail('Amount below minimum withdrawal limit', 400);
        }

        // Check maximum single withdrawal amount
        if (bccomp($maxAmount, '0', 4) > 0 && bccomp($platformAmount, $maxAmount, 4) > 0) {
            return $this->fail('Amount exceeds maximum withdrawal limit', 400);
        }

        // Check daily withdrawal limit
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

        // Check monthly withdrawal limit
        if (bccomp($monthlyLimit, '0', 4) > 0) {
            $monthSum = WithdrawOrder::where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->whereBetween('created_at', [
                    date('Y-m-01'),
                    date('Y-m-t 23:59:59'),
                ])
                ->sum('platform_amount');

            $monthTotalAfter = bcadd((string) $monthSum, $platformAmount, 4);
            if (bccomp($monthTotalAfter, $monthlyLimit, 4) > 0) {
                return $this->fail('Monthly withdrawal limit exceeded', 400);
            }
        }

        // Calculate withdrawal fee: fee = min(platform_amount * fee_pct/100, fee_max)
        $fee = '0';
        if (bccomp($feePct, '0', 4) > 0) {
            $fee = bcmul($platformAmount, bcdiv($feePct, '100', 4), 4);
            if (bccomp($feeMax, '0', 4) > 0 && bccomp($fee, $feeMax, 4) > 0) {
                $fee = $feeMax;
            }
        }
        $actualAmount = bcsub($platformAmount, $fee, 4);

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

            // Determine auto-approve using tiered threshold
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
            $order->fiat_amount     = $actualAmount;
            $order->method          = $method;
            $order->account_info    = $accountInfo;
            $order->status          = $status;
            $order->save();

            // Refresh wallet to get balance after deduction
            $wallet->refresh();
            $balanceAfter = $wallet->balance;

            // Build remark with fee info
            $remark = "Withdraw via {$method}";
            if (bccomp($fee, '0', 4) > 0) {
                $remark .= " (fee: {$fee})";
            }

            // Create transaction record
            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $userId;
            $transaction->type          = 'withdraw';
            $transaction->amount        = '-' . $platformAmount;
            $transaction->balance_after = $balanceAfter;
            $transaction->ref_type      = 'withdraw_order';
            $transaction->ref_id        = $order->id;
            $transaction->remark        = $remark;
            $transaction->save();

            Db::commit();

            return $this->success([
                'order_id'        => $this->encodeId($order->id),
                'order_no'        => $order->order_no,
                'platform_amount' => $platformAmount,
                'fee'             => $fee,
                'actual_amount'   => $actualAmount,
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
