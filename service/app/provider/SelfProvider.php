<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use common\model\GamePlayLog;
use common\model\UserGameWallet;
use app\service\WalletScope;
use app\service\WalletService;
use support\Db;
use support\Log;

class SelfProvider extends GameProvider
{
    /**
     * 单 round 派奖上限倍数（业务调参点）：同 round 派奖总额 ≤ 同 round 投注总额 × 本倍数。
     * 收紧/放宽只改这一处，SDK（/api/game/settle）与第三方回调（/api/provider/settle）两条路径同时生效。
     */
    private const MAX_PAYOUT_MULTIPLIER = 100;

    /**
     * 单 round 派奖绝对上限（游戏币，业务调参点）。比率闸挡不住「自造 round + 每轮按比率拿满」：
     * 投 1 币领 100 币，换一个 round_id 就是新额度 ⇒ 铸币总量随轮次无界。
     * 这里给单局收益封顶，与比率闸取更严者生效（两个闸都过才放行）。
     */
    private const MAX_PAYOUT_PER_ROUND = '10000';

    public function getBalance(int $userId, int $gameId, int $currencyId): string
    {
        $wallet = UserGameWallet::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('currency_id', $currencyId)
            ->first();
        return $wallet ? $wallet->balance : '0.00000000';
    }

    public function bet(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        // round_id 是「扣款 → 派奖」的唯一钥匙：settle 要求它非空、且必须匹配到本 round 的投注行。
        // 空 round 的投注扣了钱却永远无法结算（等于给用户造废数据），故在扣款之前 fail-loud。
        if ($roundId === '') {
            return ['success' => false, 'transaction_id' => '', 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'error' => 'round_id required'];
        }

        // M1: 统一钱包入口（游戏币作用域），余额不足返回 false，无残留写入
        $ok = WalletService::mutate(
            $userId,
            WalletScope::game($gameId, $currencyId),
            '-' . $amount,
            'game_spend',
            'game_round',
            0,
            'bet ' . $roundId
        );

        if (!$ok) {
            return ['success' => false, 'transaction_id' => '', 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'error' => 'Insufficient balance'];
        }

        return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId))];
    }

    /**
     * 结算 / 退款共用入口：两条路径的差异只有「上限策略」这一个入参，别复制第二份闸序。
     *
     * @param bool $refundPrincipal true=退款，上限取本局投注总额（退回本金），不受派奖闸约束；
     *                              false=派奖，比率闸与绝对闸取更严者生效。
     */
    public function settle(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = [], bool $refundPrincipal = false): array
    {
        // 闸 1：金额规范化。客户端可传 '+-100'/'abc'/'1e5'，直接拼进 bcmath 会抛 ValueError 变 500
        // 留一份客户端原文：规范化会把它变成 null 或定点串，拒绝日志要记的是「客户端到底发了什么」
        $rawAmount = $amount;
        $amount = $this->normalizeAmount($amount);
        if ($amount === null) {
            return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'Invalid amount');
        }

        // 闸 2：round_id 必须非空。空串会让下面的幂等查询整体跳过，同一局可被无限重放铸币
        if ($roundId === '') {
            return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'round_id required');
        }

        // 单次尝试，**别在这里加 `, 3`**：本方法在生产里总被两个 controller 的外层事务包着，而 Laravel 对
        // 嵌套事务的并发错误（1213 死锁 / 1205 锁等待超时）直接抛出、不重试（ManagesTransactions），写在这
        // 里的重试次数一次也不会生效。实测差分：只留内层重试时 10 个 round 出 10 个 1213 进程 fatal、只有
        // 10/20 条响应存活；外层重试才 0 fatal、20/20 响应、每 round 恰一次入账（/tmp/gp_settle_conc/reading_nw_*.txt）。
        // 重试只留在最外层（两个 controller 的 `Db::transaction(..., 3)`），此处不留「看起来在防」的摆设。
        // 钱包行缺失时上面那把钱包锁只拿到间隙锁、与「隐式建户」的 INSERT 意向锁可成环 -> 1213；整段重放由
        // 外层回收：事务已整体回滚、幂等与锁读都在前，第二次进来钱包行已存在，退化成普通行锁并串行化。
        return Db::transaction(function () use ($userId, $gameId, $currencyId, $amount, $rawAmount, $roundId, $refundPrincipal) {
            // 锁序统一（别把这行挪到流水锁读之后）：bet 是「先锁钱包行、后插流水」，本方法若反过来先锁流水、
            // 再在入账时锁钱包，两条路径就成 AB-BA 环 -> 真并发下 MySQL 报 1213 死锁（已两进程实测）。
            // 先拿钱包行锁，再拿流水锁读，两条路径同序，且「锁读先于 exists()」的约束不变。
            // 行集必须与 WalletService::apply() 的账户行完全一致（user_game_wallet + user_id/game_id/currency_id），
            // 对不上就是锁了别的行、等于没锁；账户不存在时这里只拿到间隙锁，串行化仍由下面的流水锁读兜底。
            UserGameWallet::where('user_id', $userId)
                ->where('game_id', $gameId)
                ->where('currency_id', $currencyId)
                ->lockForUpdate()
                ->get();

            // 并发串行化（顺序是要害，别调换）：先对同 round 的投注行做「当前读」加锁，再查幂等。
            // REPEATABLE READ 的读视图由事务内第一条一致性读建立，而锁读是当前读、不建立读视图；
            // 先锁读、后 exists()，并发对手提交之后这里才建视图，才能看到它写的 settle 流水并被幂等拦下。
            // 反过来先 exists() 再锁：读视图在对手提交前就固定了，幂等形同虚设，同 round 会被并发重复入账。
            GamePlayLog::where('user_id', $userId)
                ->where('game_id', $gameId)
                ->where('round_id', $roundId)
                ->where('action', 'bet')
                ->lockForUpdate()
                ->get();

            // 幂等：同一 round 只能结算/退款一次。GamePlayLog（ProviderController 在同一外层事务中插入）
            // 在提交后对重放可见，重放直接跳过入账
            if (GamePlayLog::where('user_id', $userId)
                    ->where('game_id', $gameId)
                    ->where('round_id', $roundId)
                    ->whereIn('action', ['settle', 'refund'])
                    ->exists()) {
                return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'win_amount' => '0', 'already_processed' => true];
            }

            // 必须存在同 round 的投注：没有投注的 round 无从派奖、也无从退款。
            // 取值必须走 CAST(... AS CHAR)：->sum() 可能返回 float，违反「金额一律 bcmath、禁 float」规则
            $betTotal = GamePlayLog::where('user_id', $userId)
                ->where('game_id', $gameId)
                ->where('round_id', $roundId)
                ->where('action', 'bet')
                ->selectRaw('CAST(COALESCE(SUM(bet_amount), 0) AS CHAR) AS t')
                ->value('t');
            $betTotal = is_string($betTotal) && $betTotal !== '' ? $betTotal : '0';

            if (bccomp($betTotal, '0', 8) <= 0) {
                return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'No bet for this round');
            }

            // 上限策略（单点：两条路径在同一处闸序里按入参选上限，别在 refund() 里复制一份）：
            // 退款 = 退回本金，其自然上限就是本局投注总额，故不受派奖闸约束 —— bet() 没有下注上限，
            // 若退款也被 MAX_PAYOUT_PER_ROUND 管住，一笔 20000 的合法下注就永远退不回来（本金卡死）。
            if ($refundPrincipal) {
                if (bccomp($amount, $betTotal, 8) > 0) {
                    return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'Refund exceeds round bet');
                }
            } else {
                // 闸 3：派奖额 ≤ 投注额 × MAX_PAYOUT_MULTIPLIER，防止客户端直接 settle 任意金额铸币
                if (bccomp($amount, bcmul($betTotal, (string) self::MAX_PAYOUT_MULTIPLIER, 8), 8) > 0) {
                    return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'Payout exceeds round limit');
                }
                // 闸 4：单 round 绝对上限。比率闸挡得住「投 1 派 1 万」，挡不住「每轮都按比率拿满」——
                // round 由游戏自造、轮次无上限，铸币总量照样无界；这里按单局绝对额封顶（与比率闸取更严者生效）
                if (bccomp($amount, self::MAX_PAYOUT_PER_ROUND, 8) > 0) {
                    return $this->reject($userId, $gameId, $currencyId, $roundId, $rawAmount, 'Payout exceeds per-round limit');
                }
            }

            // M1: 统一钱包入口（游戏币作用域）。结算为信用入账，账户缺失时隐式建户。
            $ok = WalletService::mutate(
                $userId,
                WalletScope::game($gameId, $currencyId),
                '+' . $amount,
                'game_earn',
                'game_round',
                0,
                'settle ' . $roundId
            );

            return ['success' => $ok, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'win_amount' => $amount];
        });
    }

    /**
     * 金额原文语法闸：bcmath 对非十进制字面量（'abc'/'1e5'/'+-100'）抛 ValueError，直接冒到调用方就是 500。
     * 控制器与 normalizeAmount 共用这一处实现（别写第二份）；只认字符串：JSON 数字/数组/null 同样不是本接口
     * 的合法入参（契约为「精确字符串」），一律判非法由调用方 fail-loud。
     */
    public static function isAmountSyntaxValid(mixed $amount): bool
    {
        if (!is_string($amount)) {
            return false;
        }

        try {
            bcadd($amount, '0', 8);
        } catch (\ValueError $e) {
            return false;
        }

        return true;
    }

    /**
     * 金额规范化（语法判定复用 isAmountSyntaxValid，本方法只管正负）：非法字符串一律返回 null 由调用方拒绝，
     * 空串经 bcadd 得 '0'，非法或 ≤ 0 同样返回 null。
     */
    private function normalizeAmount(string $amount): ?string
    {
        if (!self::isAmountSyntaxValid($amount)) {
            return null;
        }

        $normalized = bcadd($amount, '0', 8);

        return bccomp($normalized, '0', 8) > 0 ? $normalized : null;
    }

    /**
     * 结算类拒绝信封：形状与 bet() 的 Insufficient balance 失败分支一致。
     * 顺带留痕：controller 只在 success 时写流水，被闸挡下的铸币尝试否则对运维完全不可见。
     * ponytail: 拒绝日志不限流、不落库，探测者可以用大量非法请求刷日志；真要限流应放在入口中间件，不在这里。
     * 取舍（lead 已裁）：这里不包 try/catch —— 日志 handler 自身失败（磁盘满等）会让拒绝变成 500，
     * 但那仍是安全失败（不入账、可重试），为它加容错不值当。
     */
    private function reject(int $userId, int $gameId, int $currencyId, string $roundId, string $amount, string $error): array
    {
        // amount 此处可能还是客户端原文（闸 1 之前是任意长字符串），截断后再入日志，避免用超长入参灌日志
        Log::warning('SelfProvider settle rejected', [
            'user_id'     => $userId,
            'game_id'     => $gameId,
            'currency_id' => $currencyId,
            'round_id'    => $roundId,
            'amount'      => substr($amount, 0, 32),
            'reason'      => $error,
        ]);

        return [
            'success'        => false,
            'transaction_id' => '',
            'balance_after'  => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)),
            'win_amount'     => '0',
            'error'          => $error,
        ];
    }

    public function refund(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, string $reason): array
    {
        // 上限策略显式传入：默认值是派奖闸，退款靠默认值就会把大额本金卡死
        return $this->settle($userId, $gameId, $currencyId, $sessionId, $amount, $roundId, ['reason' => $reason], refundPrincipal: true);
    }

    public function rollback(int $userId, int $gameId, int $currencyId, string $sessionId, string $roundId): array
    {
        return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => $this->getBalance($userId, $gameId, $currencyId)];
    }

    public function verifySignature(array $payload, string $signature): bool
    {
        return true;
    }
}
