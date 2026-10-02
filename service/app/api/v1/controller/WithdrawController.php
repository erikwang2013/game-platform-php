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
use erikwang2013\apidoc\annotation as Apidoc;
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

#[Apidoc\Title("提现管理")]
#[Apidoc\Group("withdraw")]
class WithdrawController extends BaseController
{
    #[Apidoc\Title("提现申请")]
    #[Apidoc\Url("/api/v1/withdraw/apply")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "platform_amount", type: "float", require: true, desc: "提现金额")]
    #[Apidoc\Param(name: "method", type: "string", require: true, desc: "提现方式(paypal/bank/crypto)")]
    #[Apidoc\Param(name: "account_info", type: "string", require: true, desc: "提现账户信息")]
    #[Apidoc\Param(name: "captcha_key", type: "string", require: true, desc: "点击验证码 key")]
    #[Apidoc\Param(name: "clicks", type: "array", require: true, desc: "点击坐标集合，元素含 x/y")]
    public function apply(Request $request): Response
    {
        // Check global withdraw switch
        $globalSwitch = PlatformConfig::get('withdraw', 'global_switch');
        if (!$globalSwitch) {
            return $this->fail(trans('Withdrawal is currently disabled'), 403);
        }

        $validator = validator($request->all(), [
            'platform_amount' => 'required|numeric|min:0.0001',
            'method'          => 'required|in:paypal,bank,crypto',
            'account_info'    => 'required',
            'captcha_key'     => 'required|string',
            'clicks'          => 'required|array|min:2',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 提现是资金出口，必须过验证码（与登录/注册同一道闸）
        if (!$this->captchaOk($request)) {
            return $this->fail(trans('Incorrect captcha, please try again'), 422);
        }

        $userId         = $request->userId;
        $platformAmount = (string) $request->input('platform_amount');
        $method         = $request->input('method');
        $accountInfo    = $request->input('account_info');

        // 按用户串行化申请，防止日/月限额 check-then-act 并发突破
        // 锁值必须是**每请求唯一**的属主 token：原先固定 '1' + finally 无条件 del ⇒ 第一个请求
        // 超过 15s（锁过期）后第二个请求拿到锁，第一个的 finally 会把**第二个的锁**删掉，
        // 第三个请求随即进来 ⇒ 限额的 check-then-act 又出现窗口。
        $lockKey   = "withdraw:apply:{$userId}";
        $lockToken = bin2hex(random_bytes(16));
        try {
            $locked = Redis::set($lockKey, $lockToken, 'EX', 15, 'NX');
            if (!$locked) {
                return $this->fail(trans('Withdrawal request in progress, please retry'), 429);
            }
        } catch (\Throwable $e) {
            Log::error('Withdraw apply lock Redis failed (fail-closed): ' . $e->getMessage());
            return $this->fail(trans('Withdrawal temporarily unavailable'), 503);
        }

        try {
            return $this->applyLocked($request, $userId, $platformAmount, $method, $accountInfo);
        } finally {
            try {
                self::releaseLockIfOwned($lockKey, $lockToken);
            } catch (\Throwable $e) {
                Log::warning('Withdraw apply unlock Redis failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * 只删自己的锁：比对与删除必须在同一段 Lua 里原子完成（照 RateLimit.php:51-60 的 Redis::eval
     * 用法），否则「比对」与「删除」之间仍有窗口。非属主时返回 0、不删（锁已过期、别人拿到了锁
     * ⇒ 删它＝把别人的并发闸门拆掉，限额的 check-then-act 窗口又回来）。
     *
     * 独立成方法是为了让「属主语义」可被反射直接钉住（同 WithdrawQuoteTest 对 withdrawQuote 的做法）；
     * 调用点本身由 WithdrawLockOwnershipTest 的端到端用例兜住。
     */
    private static function releaseLockIfOwned(string $key, string $token): void
    {
        Redis::eval(
            "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end",
            1,
            $key,
            $token
        );
    }

    private function applyLocked(Request $request, int $userId, string $platformAmount, string $method, $accountInfo): Response
    {
        // 合规钩子（默认 no-op，config/compliance.php enabled=false 时与改造前行为完全一致）
        ComplianceCheckService::beforeWithdraw($userId, $platformAmount, (string) $method, $this->resolveCountry($request));

        // Check KYC-based tiered limits
        $identity = UserIdentity::where('user_id', $userId)->first();
        $level    = self::withdrawLevel($identity?->status);
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

        // 金额校验 + 手续费 + 自动审核阈值资格：纯函数（见 withdrawQuote），此处把两处来源归一为显式入参
        $quote = self::withdrawQuote($platformAmount, [
            'single_min'             => (string) $minAmount,
            'single_max'             => (string) $maxAmount,
            'auto_approve_threshold' => (string) $autoThreshold,
            'fee_pct'                => (string) $feePct,
            'fee_max'                => (string) $feeMax,
        ], VipService::getWithdrawFeeDiscount($userId));

        if ($quote['error'] !== null) {
            return $this->fail($quote['error'], $quote['http']);
        }

        $fee          = $quote['fee'];
        $actualAmount = $quote['actual_amount'];

        // 业务闸（与报价计算器分开）：withdrawQuote 保持纯计算，fee_pct=100 时照旧返回 0.0000
        // （WithdrawQuoteTest 用 Reflection 直接钉那个算术，不经过这里），但**永远不允许生成
        // fiat_amount=0 的订单**——0 是坏数据（PayoutService 拒付）⇒ 等于给用户一个「提得出去、
        // 打不出去」的单。放在风控检查之前：金额非法时不得触发风控侧的写入。
        if (bccomp($actualAmount, '0', 4) <= 0) {
            return $this->fail(trans('Fee exceeds withdrawal amount'), 400);
        }

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
            return $this->fail(trans('Withdrawal blocked by risk control: account restricted'), 403);
        }
        if ($band === 'restrict') {
            $riskReview = true;
        }

        Db::beginTransaction();

        try {
            $wallet = UserWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet || bccomp((string) $wallet->balance, $platformAmount, 4) < 0) {
                Db::rollBack();
                return $this->fail(trans('Insufficient balance'), 400);
            }

            $counted = ['pending', 'approved', 'processing', 'completed', 'manual_review'];
            if (bccomp($dailyLimit, '0', 4) > 0) {
                // 半开区间：本仓有两种日边界写法（此处半开 `< 次日 00:00:00`；ReportController::dailyRows 用
                // 闭区间 `23:59:59`）。今天在 DATETIME(0) 列上等价；表若改成 DATETIME(3)，闭区间那版会
                // 静默漏掉末秒的小数部分，故新代码一律半开。
                $dayStart     = date('Y-m-d') . ' 00:00:00';
                $nextDayStart = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
                $todaySum = WithdrawOrder::where('user_id', $userId)
                    ->whereIn('status', $counted)
                    ->where('created_at', '>=', $dayStart)
                    ->where('created_at', '<', $nextDayStart)
                    ->sum('platform_amount');
                if (self::exceedsLimit((string) $todaySum, $platformAmount, $dailyLimit)) {
                    Db::rollBack();
                    return $this->fail(trans('Daily withdrawal limit exceeded'), 400);
                }
            }
            if (bccomp($monthlyLimit, '0', 4) > 0) {
                // 与上面日窗口同一条约定（半开 + 两端都写全 00:00:00）：左端原先只有 date('Y-m-01')，
                // 之所以成立是**靠 MySQL 把 DATE 隐式补零**；右端 23:59:59 同理是漏掉末秒小数部分的写法。
                // 同一个方法里两种边界写法比跨文件更坏，故与上面日窗口统一。
                $monthStart     = date('Y-m-01') . ' 00:00:00';
                $nextMonthStart = date('Y-m-01 00:00:00', strtotime('first day of next month'));
                $monthSum = WithdrawOrder::where('user_id', $userId)
                    ->whereIn('status', $counted)
                    ->where('created_at', '>=', $monthStart)
                    ->where('created_at', '<', $nextMonthStart)
                    ->sum('platform_amount');
                if (self::exceedsLimit((string) $monthSum, $platformAmount, $monthlyLimit)) {
                    Db::rollBack();
                    return $this->fail(trans('Monthly withdrawal limit exceeded'), 400);
                }
            }

            // Determine auto-approve using tiered threshold（风控警告一律人工审核）
            $status = 'pending';
            $dualOn = in_array((string) PlatformConfig::get('withdraw', 'require_dual_review', 'off'), ['on', '1', 'true'], true);
            if (!$riskReview && !$dualOn && $quote['auto_approve_eligible']) {
                $status = 'approved';
            }

            // Generate order number: WTH + YmdHis + unique suffix
            // uniqid 微秒+进程后缀避免同秒撞 uk_order_no
            $orderNo = self::generateOrderNo('WTH', time(), self::orderNoSuffix());

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
                return $this->fail(trans('Failed to deduct balance'), 500);
            }

            // Refresh wallet to get balance after deduction
            $wallet->refresh();
            $balanceAfter = $wallet->balance;

            // Build remark with fee info
            $remark = "Withdraw via {$method}";
            if (bccomp($fee, '0', 4) > 0) {
                $remark .= " (fee: {$fee})";
            }

            // 语义：这里是"申请"不是"完成"，completed 由 PayoutService::markCompleted 在打款成功时发出。
            // 必须在 Db::commit() 之前 push：push 并入当前事务（transactionLevel()>0），订单/流水行与
            // outbox 行同提交；原先的 emit() 在 commit 之后走 Pub/Sub，两者之间进程崩溃 ⇒ 已扣款而事件
            // 永久丢失且无重放。eventId 拼法照 plans/2026-08-31-event-reliable-delivery-plan.md:135。
            EventBus::push('withdraw.applied', "withdraw_{$order->id}_{$status}", [
                'user_id'         => $userId,
                'platform_amount' => $platformAmount,
                'status'          => $status,
            ]);

            Db::commit();

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
            return $this->fail(trans('Withdrawal failed'), 500);
        }
    }

    #[Apidoc\Title("提现记录")]
    #[Apidoc\Url("/api/v1/withdraw/orders")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function orders(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $paginator = WithdrawOrder::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
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

    /**
     * 提现层级判定：KYC 已通过（approved）→ verified 档，其余（未提交/审核中/驳回/未知状态）→ default 档。
     *
     * 决定 WithdrawLimit::getByLevel() 取哪一行限额（单笔上下限/日/月上限/手续费/自动审核阈值）。
     * 纯函数，原先内联在 applyLocked 中。
     */
    private static function withdrawLevel(?string $identityStatus): string
    {
        return $identityStatus === 'approved' ? 'verified' : 'default';
    }

    /**
     * 提现报价：单笔最小/最大限额校验 + 手续费（VIP 折扣、封顶）+ 自动审核阈值资格。
     *
     * 纯函数（输入显式，不碰 $request/DB/Redis），原先内联在 applyLocked 中。
     * 与调用方的分工：
     *  - 日/月累计限额比较见 exceedsLimit()；
     *  - 风控警告与「双重审核开关」仍在调用方参与 status 判定 —— 风险检查发生在金额校验
     *    之后，顺序不可调换（金额非法时不得触发风控检查的写入）。
     *
     * @param array{single_min:string,single_max:string,auto_approve_threshold:string,fee_pct:string,fee_max:string} $terms
     * @return array{error:?string,http:?int,fee:string,actual_amount:string,auto_approve_eligible:bool}
     */
    private static function withdrawQuote(string $platformAmount, array $terms, string $vipFeeDiscount): array
    {
        $minAmount     = $terms['single_min'];
        $maxAmount     = $terms['single_max'];
        $autoThreshold = $terms['auto_approve_threshold'];
        $feePct        = $terms['fee_pct'];
        $feeMax        = $terms['fee_max'];

        $quote = ['error' => null, 'http' => null, 'fee' => '0', 'actual_amount' => $platformAmount, 'auto_approve_eligible' => false];

        // Check minimum withdrawal amount
        if (bccomp($platformAmount, $minAmount, 4) < 0) {
            $quote['error'] = 'Amount below minimum withdrawal limit';
            $quote['http']  = 400;
            return $quote;
        }

        // Check maximum single withdrawal amount
        if (bccomp($maxAmount, '0', 4) > 0 && bccomp($platformAmount, $maxAmount, 4) > 0) {
            $quote['error'] = 'Amount exceeds maximum withdrawal limit';
            $quote['http']  = 400;
            return $quote;
        }

        // Calculate withdrawal fee with VIP discount: fee = min(platform_amount * fee_pct/100 * (1-vip_discount), fee_max)
        $fee = '0';
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
        // 下界：手续费不得吃穿本金。档位行可能被写坏或历史遗留（fee_pct 列上限 999.99，可造出
        // fee ≈ 10×金额），而实收为负会被 PayoutService 当成「未设置」回退成全额照付 ⇒ 手续费
        // 静默丢失。== 0 仍放行：fee_pct=100 时实收恰为 0，该边界由 WithdrawQuoteTest 钉死。
        $actualAmount = bcsub($platformAmount, $fee, 4);
        if (bccomp($actualAmount, '0', 4) < 0) {
            $quote['error'] = 'Fee exceeds withdrawal amount';
            $quote['http']  = 400;
            return $quote; // 同其余驳回路径：fee 保持初值、actual_amount 原样回显
        }

        $quote['fee']           = $fee;
        $quote['actual_amount'] = $actualAmount;

        // 自动审核阈值资格（riskReview / dualOn 由调用方合取）
        $quote['auto_approve_eligible'] =
            bccomp($autoThreshold, '0', 4) > 0 && bccomp($platformAmount, $autoThreshold, 4) < 0;

        return $quote;
    }

    /**
     * 日/月累计限额判定：已用量 + 本次金额 是否超过限额。
     *
     * 调用方负责 `bccomp($limit, '0', 4) > 0` 的短路（限额为 0 = 不限制时不应发起 sum 查询）。
     */
    private static function exceedsLimit(string $usedSum, string $amount, string $limit): bool
    {
        return bccomp(bcadd($usedSum, $amount, 4), $limit, 4) > 0;
    }
}
