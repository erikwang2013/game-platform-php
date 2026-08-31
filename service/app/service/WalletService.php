<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use common\SnowflakeService;
use app\event\EventBus;
use app\model\Transaction;
use support\Db;

/**
 * 统一钱包唯一入口（M1）：平台币 + 游戏币共用一条写路径、一条流水。
 *
 * 写操作恒为「锁账户行 → 改余额 → 写流水 → 发事件」同一事务，
 * 余额与 game_transaction 不可分叉。
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

    // 表名不带 game_ 前缀（config/database.php 已配 prefix）
    private const TABLE_PLATFORM = 'user_wallet';
    private const TABLE_GAME = 'user_game_wallet';

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
     * 冻结：available -= n, frozen += n，流水 type=lock。
     */
    public static function lock(int $userId, WalletScope $s, string $amount, string $refType, int $refId): bool
    {
        $amount = self::str($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            return false;
        }

        return self::doMutate($userId, $s, '-' . $amount, self::TYPE_LOCK, $refType, $refId, '冻结余额', false, false);
    }

    /**
     * 解冻：frozen -= n, available += n，流水 type=unlock。
     */
    public static function unlock(int $userId, WalletScope $s, string $amount): bool
    {
        $amount = self::str($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            return false;
        }

        return self::doMutate($userId, $s, $amount, self::TYPE_UNLOCK, '', 0, '解冻余额', true, false);
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
     * $fromFrozen=true 时改动冻结列（unlock 专用）；
     * $trackStats=false 用于 lock/unlock/reconcile（桶间转移或修正，不计累计收支）。
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
     *
     * @return array{ok:bool,balance_after:string}
     */
    private static function apply(int $userId, WalletScope $s, string $delta, bool $fromFrozen): array
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
            if (bccomp($delta, '0', self::SCALE) > 0) {
                $row['total_earned'] = bcadd(self::str((string) $row['total_earned']), $delta, self::SCALE);
            } elseif (bccomp($delta, '0', self::SCALE) < 0) {
                $row['total_spent'] = bcadd(self::str((string) $row['total_spent']), ltrim($delta, '-'), self::SCALE);
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
     * 写流水行 + 发事件。record 失败即抛异常 → 外层事务回滚余额写入。
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
        Transaction::create([
            'id'            => SnowflakeService::generate(),
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

        // EventBus::emit 内部已吞异常，不会回滚资金事务
        EventBus::emit('wallet.mutated', [
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

    private static function str(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
