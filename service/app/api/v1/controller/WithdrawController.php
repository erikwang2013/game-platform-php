<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\PlatformConfig;
use common\model\UserIdentity;
use common\model\UserWallet;
use common\model\WithdrawLimit;
use common\model\WithdrawOrder;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;
use app\event\EventBus;
use app\service\AntiCheatService;
use app\service\ComplianceCheckService;
use common\service\NotificationService;
use app\service\RiskService;
use common\service\VipService;

/**
 * @Apidoc\Title("提现管理")
 * @Apidoc\Group("withdraw")
 */
class WithdrawController extends BaseController
{
    /**
     * @Apidoc\Title("提现申请")
     * @Apidoc\Url("/api/v1/withdraw/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="platform_amount", type="float", require=true, desc="提现金额")
     * @Apidoc\Param(name="method", type="string", require=true, desc="提现方式(paypal/bank/crypto)")
     * @Apidoc\Param(name="account_info", type="string", require=true, desc="提现账户信息")
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

        // 按用户串行化申请，防止日/月限额 check-then-act 并发突破
        $lockKey = "withdraw:apply:{$userId}";
        try {
            $locked = Redis::set($lockKey, '1', 'EX', 15, 'NX');
            if (!$locked) {
                return $this->fail('Withdrawal request in progress, please retry', 429);
            }
        } catch (\Throwable $e) {
            Log::error('Withdraw apply lock Redis failed (fail-closed): ' . $e->getMessage());
            return $this->fail('Withdrawal temporarily unavailable', 503);
        }

        try {
            return $this->applyLocked($request, $userId, $platformAmount, $method, $accountInfo);
        } finally {
            try {
                Redis::del($lockKey);
            } catch (\Throwable $e) {
                Log::warning('Withdraw apply unlock Redis failed: ' . $e->getMessage());
            }
        }
    }

    private function applyLocked(Request $request, int $userId, string $platformAmount, string $method, $accountInfo): Response
    {
        // 合规钩子（默认 no-op，config/compliance.php enabled=false 时与改造前行为完全一致）
        ComplianceCheckService::beforeWithdraw($userId, $platformAmount, (string) $method, $this->resolveCountry($request));

        // Check KYC-based tiered limits
        $level    = 'default';
        $identity = UserIdentity::where('user_id', $userId)->first();
        if ($identity && $identity->status === 'approved') {
            $level = 'verified';
        }
        // VIP fee discount applied below

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

        // Calculate withdrawal fee with VIP discount: fee = min(platform_amount * fee_pct/100 * (1-vip_discount), fee_max)
        $fee = '0';
        $vipFeeDiscount = VipService::getWithdrawFeeDiscount($userId);
        if (bccomp($feePct, '0', 4) > 0) {
            $effectiveFeePct = $feePct;
            if (bccomp($vipFeeDiscount, '0', 4) > 0) {
                $effectiveFeePct = bcmul($feePct, bcsub('1', $vipFeeDiscount, 4), 4);
                if (bccomp($effectiveFeePct, '0', 4) < 0) $effectiveFeePct = '0';
            }
            $fee = bcmul($platformAmount, bcdiv($effectiveFeePct, '100', 4), 4);
            if (bccomp($feeMax, '0', 4) > 0 && bccomp($fee, $feeMax, 4) > 0) {
                $fee = $feeMax;
            }
        }
        $actualAmount = bcsub($platformAmount, $fee, 4);

        // 风控检查（H4）：阻断 → 拒绝下单；警告 → 人工审核，不自动放行
        $riskReview = false;
        $risk = RiskService::check($userId, 'withdraw', [
            'amount'          => $platformAmount,
            'ip'              => (string) $request->getRealIp(),
            'user_agent'      => (string) $request->header('user-agent', ''),
            'accept_lang'     => (string) $request->header('accept-language', ''),
            'accept_encoding' => (string) $request->header('accept-encoding', ''),
        ]);
        if ($risk['result'] === 'block') {
            return $this->fail('Withdrawal blocked by risk control: ' . $risk['message'], 403);
        }
        if ($risk['result'] === 'warn') {
            $riskReview = true;
        }

        // 反作弊信任带位联动（在 H4 风控检查之后）：freeze → 直接拒绝；restrict → 转人工审核
        $band = AntiCheatService::trustBand($userId);
        if ($band === 'freeze') {
            return $this->fail('Withdrawal blocked by risk control: account restricted', 403);
        }
        if ($band === 'restrict') {
            $riskReview = true;
        }

        Db::beginTransaction();

        try {
            $wallet = UserWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet || bccomp((string) $wallet->balance, $platformAmount, 4) < 0) {
                Db::rollBack();
                return $this->fail('Insufficient balance', 400);
            }

            $counted = ['pending', 'approved', 'processing', 'completed', 'manual_review'];
            if (bccomp($dailyLimit, '0', 4) > 0) {
                $todaySum = WithdrawOrder::where('user_id', $userId)
                    ->whereIn('status', $counted)
                    ->whereDate('created_at', date('Y-m-d'))
                    ->sum('platform_amount');
                if (bccomp(bcadd((string) $todaySum, $platformAmount, 4), $dailyLimit, 4) > 0) {
                    Db::rollBack();
                    return $this->fail('Daily withdrawal limit exceeded', 400);
                }
            }
            if (bccomp($monthlyLimit, '0', 4) > 0) {
                $monthSum = WithdrawOrder::where('user_id', $userId)
                    ->whereIn('status', $counted)
                    ->whereBetween('created_at', [
                        date('Y-m-01'),
                        date('Y-m-t 23:59:59'),
                    ])
                    ->sum('platform_amount');
                if (bccomp(bcadd((string) $monthSum, $platformAmount, 4), $monthlyLimit, 4) > 0) {
                    Db::rollBack();
                    return $this->fail('Monthly withdrawal limit exceeded', 400);
                }
            }

            // Determine auto-approve using tiered threshold（风控警告一律人工审核）
            $status = 'pending';
            $dualOn = in_array((string) PlatformConfig::get('withdraw', 'require_dual_review', 'off'), ['on', '1', 'true'], true);
            if (!$riskReview && !$dualOn && bccomp($autoThreshold, '0', 4) > 0 && bccomp($platformAmount, $autoThreshold, 4) < 0) {
                $status = 'approved';
            }

            // Generate order number: WTH + YmdHis + random 4 digits
            // uniqid 微秒+进程后缀避免同秒撞 uk_order_no
            $orderNo = 'WTH' . date('YmdHis') . strtoupper(substr(uniqid('', true), -6));

            // Create withdraw order first（扣款需以订单 id 作为流水 ref_id）
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

            // Deduct balance（WalletService 统一记账，ref 关联提现单）
            $deducted = UserWallet::deductBalance($userId, $platformAmount, 'withdraw', 'withdraw_order', (int) $order->id);
            if (!$deducted) {
                Db::rollBack();
                return $this->fail('Failed to deduct balance', 500);
            }

            // Refresh wallet to get balance after deduction
            $wallet->refresh();
            $balanceAfter = $wallet->balance;

            // Build remark with fee info
            $remark = "Withdraw via {$method}";
            if (bccomp($fee, '0', 4) > 0) {
                $remark .= " (fee: {$fee})";
            }

            Db::commit();

            // 语义：这里是"申请"不是"完成"，completed 由 PayoutService::markCompleted 在打款成功时发出
            EventBus::emit('withdraw.applied', ['user_id' => $userId, 'platform_amount' => $platformAmount, 'status' => $status]);

            NotificationService::send($userId, 'withdraw', 'Withdrawal Request Submitted', "Withdrawal of {$platformAmount} platform tokens submitted ({$status})", 'withdraw_order', $order->id);

            return $this->success([
                'order_id'        => $this->encodeId($order->id),
                'order_no'        => $order->order_no,
                'platform_amount' => $platformAmount,
                'fee'             => $fee,
                'actual_amount'   => $actualAmount,
                'status'          => $status,
                'balance_after'   => $balanceAfter,
                'created_at'      => $order->created_at,
            ]);
        } catch (\Throwable $e) {
            Db::rollBack();
            Log::error('Withdraw apply failed: ' . $e->getMessage());
            return $this->fail('Withdrawal failed', 500);
        }
    }

    /**
     * @Apidoc\Title("提现记录")
     * @Apidoc\Url("/api/v1/withdraw/orders")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
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
