<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use common\SnowflakeService;
use common\service\OutboxWriter;
use common\model\Transaction;
use support\Db;

/**
 * 统一钱包唯一入口（M1）：平台币 + 游戏币共用一条写路径、一条流水。
 *
 * 写操作恒为「锁账户行 → 改余额 → 写流水 → 发事件」同一事务，
 * 余额与 game_transaction 不可分叉。
 *
 * 冻结不是单池列：`frozen_balance` 只是聚合缓存，权威台账是 `game_wallet_hold`（per-hold 子台账，
 * lock 落行 / unlock 按笔消费），不变量 `frozen_balance == Σ(hold.remaining)` 由
 * assertFreezeLedger() 在事务内断言，分叉即回滚。
 *
 * 精度：代码统一 scale 8（bcadd/bcsub）。表结构需为 DECIMAL(20,8)，
 * 否则 8 位运算结果写回被截断 —— 见 install/migrations/2026_08_31_wallet_unify.sql。
 */
class WalletService
{
    public const SCALE = 8;

    // game_transaction.type 枚举
    public const TYPE_LOCK = 'lock';
    public const TYPE_UNLOCK = 'unlock';
    public const TYPE_RECONCILE = 'reconcile';

    // game_wallet_hold.status 枚举
    public const HOLD_ACTIVE = 1;
    public const HOLD_RELEASED = 2;

    // 表名不带 game_ 前缀（config/database.php 已配 prefix）
    private const TABLE_PLATFORM = 'user_wallet';
    private const TABLE_GAME = 'user_game_wallet';
    private const TABLE_HOLD = 'wallet_hold';

    /**
     * 可用余额（不含冻结）。账户不存在时返回 0，不隐式建户。
     */
    public static function balance(int $userId, WalletScope $s): string
    {
        $row = self::find($userId, $s);

        return self::str((string) ($row['balance'] ?? 0));
    }

    /**
     * 变更余额并写流水（同事务）。delta 为带符号 bcmath 字符串。
     *
     * @return bool false = 余额不足或写入失败（无残留写入）
     */
    public static function mutate(
        int $userId,
        WalletScope $s,
        string $delta,
        string $type,
        string $refType,
        int $refId,
        string $remark = ''
    ): bool {
        return self::doMutate($userId, $s, $delta, $type, $refType, $refId, $remark, false, true);
    }

    /**
     * 冻结：available -= n, frozen += n，流水 type=lock，并落一行子台账 hold（同事务）——
     * 释放时按笔消费，不再靠「最近一笔冻结」猜。
     */
    public static function lock(int $userId, WalletScope $s, string $amount, string $refType, int $refId): bool
    {
        $amount = self::str($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            return false;
        }

        return Db::transaction(function () use ($userId, $s, $amount, $refType, $refId) {
            if (!self::doMutate($userId, $s, '-' . $amount, self::TYPE_LOCK, $refType, $refId, '冻结余额', true, false)) {
                return false;
            }

            self::openHold($userId, $s, $amount, $refType, $refId);
            self::assertFreezeLedger($userId, $s);

            return true;
        });
    }

    /**
     * 解冻：frozen -= n, available += n，流水 type=unlock，并按笔消费子台账。
     *
     * $refType/$refId = **请求释放的目标冻结**：台账里存在同 ref 的活跃 hold ⇒ 先吃它；不存在
     * （含 ''/0 这类不指向任何 hold 的取值）⇒ 退化为 FIFO（最老优先）。两者都继续按最老优先吃满 $amount。
     * 归因不再压在流水两列上：**实际**消费了哪几笔记在同笔流水的 remark（`hold:<id>,...`），
     * 逐笔份额落在 game_wallet_hold（remaining/status/released_at）。
     */
    public static function unlock(int $userId, WalletScope $s, string $amount, string $refType, int $refId): bool
    {
        $amount = self::str($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            return false;
        }

        return Db::transaction(function () use ($userId, $s, $amount, $refType, $refId) {
            // 先取钱包行锁，钉死「钱包行 → 台账行」的取锁顺序（与 lock 内 doMutate 一致）：反序可与
            // 并发的 lock（持钱包行、插台账行）成 AB-BA 环。
            $wallet = self::find($userId, $s);
            $frozen = self::str((string) ($wallet['frozen_balance'] ?? 0));

            // 先规划后落账：台账凑不满 ⇒ 尚未写任何行就返回 false（与「冻结不足」同一条零写入失败路径）
            $plan = self::planRelease($userId, $s, $amount, $refType, $refId, $frozen);
            if ($plan === null) {
                return false;
            }

            if (!self::doMutate($userId, $s, $amount, self::TYPE_UNLOCK, $refType, $refId, self::releaseRemark($plan), true, false)) {
                return false;
            }

            self::consumeHolds($plan);
            self::assertFreezeLedger($userId, $s);

            return true;
        });
    }

    /**
     * 流水游标分页（雪花ID 倒序）。cursor 为空取最新一页。
     */
    public static function ledger(int $userId, WalletScope $s, string $cursor, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $query = Transaction::where('user_id', $userId)
            ->where('scope', $s->scope)
            ->orderBy('id', 'desc')
            ->limit($limit + 1);

        if ($cursor !== '') {
            $query->where('id', '<', (int) $cursor);
        }

        $rows = $query->get(['id', 'type', 'amount', 'balance_after', 'scope', 'game_id', 'currency_id', 'ref_type', 'ref_id', 'remark', 'created_at']);
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->slice(0, $limit);
        }

        return [
            'items'       => $rows->toArray(),
            'has_more'    => $hasMore,
            'next_cursor' => $hasMore ? (string) $rows->last()->id : '',
        ];
    }

    /**
     * 事务内落账。
     * $fromFrozen=true = 桶间转移（lock/unlock）：frozen 走 -delta、available 走 +delta，两桶反向移动；
     * **不是 unlock 专用** —— lock 也必须传 true，否则冻结会静默吞掉可用余额（本批修掉的资金缺陷）。
     * $trackStats=false 用于 lock/unlock/reconcile（桶间转移或修正，不计累计收支列）。
     */
    private static function doMutate(
        int $userId,
        WalletScope $s,
        string $delta,
        string $type,
        string $refType,
        int $refId,
        string $remark,
        bool $fromFrozen,
        bool $trackStats
    ): bool {
        return Db::transaction(function () use ($userId, $s, $delta, $type, $refType, $refId, $remark, $fromFrozen, $trackStats) {
            // 余额不足 / 冻结不足时直接返回 false：此时尚未写任何行，
            // 故 Db::transaction 不需要回滚（Laravel 仅对异常回滚）。
            $result = self::apply($userId, $s, $delta, $fromFrozen, $trackStats);
            if (!$result['ok']) {
                return false;
            }

            self::record($userId, $s, $delta, $type, $result['balance_after'], $refType, $refId, $remark);

            return true;
        });
    }

    /**
     * 冻结感知地更新可用余额（调用方需保证事务 + 行锁）。
     * 先校验后建户，保证失败路径零写入。
     * $trackStats=false 时不动累计收支列（lock/unlock/reconcile 只做桶间转移或余额修正）。
     *
     * @return array{ok:bool,balance_after:string}
     */
    private static function apply(int $userId, WalletScope $s, string $delta, bool $fromFrozen, bool $trackStats): array
    {
        $delta = self::str($delta);
        $row = self::find($userId, $s);

        $balance = self::str((string) ($row['balance'] ?? 0));
        $frozen = self::str((string) ($row['frozen_balance'] ?? 0));

        $balanceAfter = bcadd($balance, $delta, self::SCALE);
        $frozenAfter = $fromFrozen ? bcsub($frozen, $delta, self::SCALE) : $frozen;

        // 拒绝负余额 / 冻结透支 —— 校验先于建户，保证失败路径零写入
        if (bccomp($balanceAfter, '0', self::SCALE) < 0 || bccomp($frozenAfter, '0', self::SCALE) < 0) {
            return ['ok' => false, 'balance_after' => $balance];
        }

        if ($row === null) {
            $row = self::create($userId, $s);
            if ($row === null) {
                return ['ok' => false, 'balance_after' => '0.00000000'];
            }
            $balance = self::str((string) $row['balance']);
            $frozen = self::str((string) $row['frozen_balance']);
            $balanceAfter = bcadd($balance, $delta, self::SCALE);
            $frozenAfter = $fromFrozen ? bcsub($frozen, $delta, self::SCALE) : $frozen;
        }

        $row['balance'] = $balanceAfter;
        $row['frozen_balance'] = $frozenAfter;
        if (!$s->isGame()) {
            $row['version'] = (int) $row['version'] + 1;
            // 累计列只认真实收支：lock/unlock 的 $trackStats=false，否则一次「冻结→解冻」会把
            // total_spent 与 total_earned 同时虚增同一笔金额（累计收支列会被下游直接展示；且管理端 hold 是全仓唯一冻结入口）
            if ($trackStats) {
                if (bccomp($delta, '0', self::SCALE) > 0) {
                    $row['total_earned'] = bcadd(self::str((string) $row['total_earned']), $delta, self::SCALE);
                } elseif (bccomp($delta, '0', self::SCALE) < 0) {
                    $row['total_spent'] = bcadd(self::str((string) $row['total_spent']), ltrim($delta, '-'), self::SCALE);
                }
            }
        }
        // ponytail: 游戏币表无 version 列，互斥靠 FOR UPDATE 行锁

        $affected = Db::table($s->isGame() ? self::TABLE_GAME : self::TABLE_PLATFORM)
            ->where('id', $row['id'])
            ->update(array_intersect_key(
                $row,
                array_flip($s->isGame()
                    ? ['balance', 'frozen_balance']
                    : ['balance', 'frozen_balance', 'total_earned', 'total_spent', 'version'])
            ));

        return ['ok' => $affected > 0, 'balance_after' => $row['balance']];
    }

    /**
     * 事务内取账户行（SELECT ... FOR UPDATE）。不存在返回 null。
     *
     * @return array<string,mixed>|null
     */
    private static function find(int $userId, WalletScope $s): ?array
    {
        $query = Db::table($s->isGame() ? self::TABLE_GAME : self::TABLE_PLATFORM)
            ->where('user_id', $userId)
            ->lockForUpdate();

        if ($s->isGame()) {
            $query->where('game_id', $s->gameId)->where('currency_id', $s->currencyId);
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /**
     * 事务内建户。创建后重新取锁读回。
     *
     * @return array<string,mixed>|null
     */
    private static function create(int $userId, WalletScope $s): ?array
    {
        $base = [
            'balance'        => '0.00000000',
            'frozen_balance' => '0.00000000',
        ];

        if ($s->isGame()) {
            Db::table(self::TABLE_GAME)->insert(array_merge($base, [
                'id' => SnowflakeService::generate(),
                'user_id' => $userId,
                'game_id' => $s->gameId,
                'currency_id' => $s->currencyId,
            ]));
        } else {
            Db::table(self::TABLE_PLATFORM)->insert(array_merge($base, [
                'id' => SnowflakeService::generate(),
                'user_id' => $userId,
                'total_earned' => '0.00000000',
                'total_spent'  => '0.00000000',
                'version'      => 0,
            ]));
        }

        return self::find($userId, $s);
    }

    /**
     * 写流水行 + 可靠投递事件。record 失败即抛异常 → 外层事务回滚余额写入。
     */
    private static function record(
        int $userId,
        WalletScope $s,
        string $delta,
        string $type,
        string $balanceAfter,
        string $refType,
        int $refId,
        string $remark
    ): void {
        $transactionId = SnowflakeService::generate();

        Transaction::create([
            'id'            => $transactionId,
            'user_id'       => $userId,
            'type'          => $type,
            'amount'        => $delta,
            'balance_after' => $balanceAfter,
            'scope'         => $s->scope,
            'game_id'       => $s->gameId,
            'currency_id'   => $s->currencyId,
            'ref_type'      => $refType,
            'ref_id'        => $refId,
            'remark'        => $remark,
        ]);

        // wallet.mutated 在 EventBus::RELIABLE_EVENTS 名单里（资产变动 ⇒ 必须可靠投递），故写 Outbox
        // 而非 Pub/Sub emit（emit 的失败只记日志，Redis 抖动/消费方异常即永久丢事件）。
        // 直调共享实现 OutboxWriter（EventBus::push 本体只有一行、转调的就是它）而不调 EventBus::push：
        // 本文件在 service/admin 两树逐字节相同（WalletServiceTwoTreeParityTest 钉着），而 admin 树的
        // EventBus 没有 push() ⇒ 走树内门面必炸一边；OutboxWriter 的 docblock 自称两树共用的唯一实现。
        // 事务性：调用方恒在事务内（doMutate 包着）⇒ 事件行并入当前事务、与余额行同生共死；写不进去会抛
        // （不吞）⇒ 资金事务跟着回滚（钱动了而事件没发属静默故障，与 .env 同级）。
        // eventId 取自本笔流水的雪花主键：同一次变动恒定、跨变动唯一（outbox 的 uk_event_id）；
        // 不稳定 ⇒ 重放产生重复事件，正是可靠投递要治的病。
        OutboxWriter::write('wallet.mutated', 'wallet.mutated:' . $transactionId, [
            'user_id'     => $userId,
            'scope'       => $s->scope,
            'game_id'     => $s->gameId,
            'currency_id' => $s->currencyId,
            'type'        => $type,
            'amount'      => $delta,
            'balance_after' => $balanceAfter,
            'ref_type'    => $refType,
            'ref_id'      => $refId,
        ]);
    }

    /** 落一行冻结子台账 hold（与余额变动同事务）；remaining 初始 = amount。 */
    private static function openHold(int $userId, WalletScope $s, string $amount, string $refType, int $refId): void
    {
        Db::table(self::TABLE_HOLD)->insert([
            'id'          => SnowflakeService::generate(),
            'user_id'     => $userId,
            'scope'       => $s->scope,
            'game_id'     => $s->gameId,
            'currency_id' => $s->currencyId,
            'amount'      => $amount,
            'remaining'   => $amount,
            'ref_type'    => $refType,
            'ref_id'      => $refId,
            'status'      => self::HOLD_ACTIVE,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 规划本次释放消费哪些 hold（只读 + 行锁，不改任何行）：目标 hold 提到队首，其余按 id 升序
     * （最老优先）；凑不满 $amount 返回 null（调用方零写入地失败）。
     *
     * @return array<int,array{id:int,remaining:string,take:string}>|null
     */
    private static function planRelease(int $userId, WalletScope $s, string $amount, string $refType, int $refId, string $frozen): ?array
    {
        $holds = Db::table(self::TABLE_HOLD)
            ->where('user_id', $userId)
            ->where('scope', $s->scope)
            ->where('game_id', $s->gameId)
            ->where('currency_id', $s->currencyId)
            ->where('status', self::HOLD_ACTIVE)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'remaining', 'ref_type', 'ref_id']);

        if ($holds->isEmpty() && bccomp($frozen, '0', self::SCALE) > 0) {
            // 钱包有冻结、台账却一笔都没有 = 回填迁移没跑，不是「余额不足」——两者运维动作不同，
            // 不能都笼统报「解冻失败」。抛 ⇒ 外层事务回滚、管理端留日志。
            throw new \RuntimeException(
                '冻结子台账为空但 frozen_balance=' . $frozen . '（user_id=' . $userId . ' scope=' . $s->scope
                . '）：请先执行 install/migrations/2026_09_28_wallet_freeze_ledger.sql 回填'
            );
        }

        $rows = $holds->all();
        if ($refType !== '' || $refId > 0) {
            foreach ($rows as $i => $hold) {
                if ((string) $hold->ref_type === $refType && (int) $hold->ref_id === $refId) {
                    unset($rows[$i]);
                    array_unshift($rows, $hold);
                    break;
                }
            }
        }

        $plan = [];
        $left = $amount;
        foreach ($rows as $hold) {
            if (bccomp($left, '0', self::SCALE) <= 0) {
                break;
            }
            $remaining = self::str((string) $hold->remaining);
            if (bccomp($remaining, '0', self::SCALE) <= 0) {
                continue;
            }
            // 吃到刚好凑满为止：不足则整笔吃完，够了则只吃差额
            $take = bccomp($remaining, $left, self::SCALE) <= 0 ? $remaining : $left;
            $plan[] = ['id' => (int) $hold->id, 'remaining' => $remaining, 'take' => $take];
            $left = bcsub($left, $take, self::SCALE);
        }

        return bccomp($left, '0', self::SCALE) === 0 ? $plan : null;
    }

    /**
     * 落账本次消费：remaining 递减，归零才 status=released + released_at（部分释放只动 remaining，
     * released_at 保持 NULL = 尚未释放完）。
     */
    private static function consumeHolds(array $plan): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($plan as $item) {
            $after = bcsub($item['remaining'], $item['take'], self::SCALE);
            $done = bccomp($after, '0', self::SCALE) === 0;
            Db::table(self::TABLE_HOLD)->where('id', $item['id'])->update([
                'remaining'   => $after,
                'status'      => $done ? self::HOLD_RELEASED : self::HOLD_ACTIVE,
                'released_at' => $done ? $now : null,
            ]);
        }
    }

    /**
     * 释放流水的 remark：写明本次实际消费了哪些 hold，使流水本身即可回答「这笔释放吃的是哪几笔冻结」。
     * 列宽 255：拼不下就退化为笔数，绝不因备注超长回滚整笔资金。
     */
    private static function releaseRemark(array $plan): string
    {
        $ids = implode(',', array_column($plan, 'id'));

        return strlen($ids) <= 200 ? '解冻余额 hold:' . $ids : '解冻余额 hold:' . count($plan) . '笔';
    }

    /**
     * 不变量断言：聚合列 frozen_balance == Σ(hold.remaining)。子台账是权威，聚合列只是缓存。
     *
     * 只在 lock/unlock 里断言 —— 它们是全仓仅有的两条冻结写路径；mutate 不碰冻结列，故热路径零额外
     * 查询。不满足即抛 ⇒ 外层事务回滚：分叉时宁可不动钱（fail-closed），也不写出一笔事后对不上的冻结。
     */
    private static function assertFreezeLedger(int $userId, WalletScope $s): void
    {
        $wallet = self::find($userId, $s);   // 复用取行读法；行锁已在事务内，重复取锁无害
        $frozen = self::str((string) ($wallet['frozen_balance'] ?? 0));

        // 逐行 bcadd 而不是 SQL SUM：SUM 回来的是 DECIMAL，Cast 成 float 就丢金额精度（本仓铁律）
        $sum = '0';
        foreach (Db::table(self::TABLE_HOLD)->where('user_id', $userId)
            ->where('scope', $s->scope)->where('game_id', $s->gameId)
            ->where('currency_id', $s->currencyId)->pluck('remaining') as $value) {
            $sum = bcadd($sum, self::str((string) $value), self::SCALE);
        }

        if (bccomp($frozen, $sum, self::SCALE) !== 0) {
            throw new \RuntimeException(sprintf(
                '冻结台账分叉：%s.frozen_balance=%s vs Σhold.remaining=%s（user_id=%d scope=%s）——'
                . '先跑 install/migrations/2026_09_28_wallet_freeze_ledger.sql 回填，再查是谁绕过 WalletService 写了冻结列',
                $s->isGame() ? 'user_game_wallet' : 'user_wallet', $frozen, $sum, $userId, $s->scope
            ));
        }
    }

    private static function str(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
