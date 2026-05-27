<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;
use Erikwang2013\Snowflake\Snowflake;
use support\Log;

/**
 * 充值/交易日志双写服务
 *
 * 将充值订单和交易流水同步到 ClickHouse，支撑收入分析和转化推荐。
 */
class DepositLogService
{
    private static ?Snowflake $snowflake = null;

    public static function logDeposit(int $orderId, int $userId, string $amount, string $currency, string $status, string $paymentMethod = ''): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            ClickHouseService::table('erik_deposit_log')->insert([[
                'id'             => self::snowflakeId(),
                'order_id'       => $orderId,
                'user_id'        => $userId,
                'amount'         => $amount,
                'currency'       => $currency,
                'status'         => $status,
                'payment_method' => $paymentMethod,
                'created_at'     => $now,
            ]]);
        } catch (\Throwable $e) {
            Log::warning('ClickHouse deposit sync failed: ' . $e->getMessage());
        }
    }

    public static function logTransaction(int $txId, int $userId, string $type, string $amount, string $balanceAfter, string $refType = '', int $refId = 0): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            ClickHouseService::table('erik_transaction_log')->insert([[
                'id'            => self::snowflakeId(),
                'tx_id'         => $txId,
                'user_id'       => $userId,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => $balanceAfter,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'created_at'    => $now,
            ]]);
        } catch (\Throwable $e) {
            Log::warning('ClickHouse transaction sync failed: ' . $e->getMessage());
        }
    }

    /**
     * 收入概览
     * @return array{total: string, count: int, avg: string}
     */
    public static function revenueOverview(int $days = 7): array
    {
        $sql = "
            SELECT
                sum(toDecimal64(amount, 4)) AS total,
                count() AS cnt,
                avg(toDecimal64(amount, 4)) AS avg_amount
            FROM erik_deposit_log
            WHERE status = 'confirmed'
              AND created_at >= now() - INTERVAL {$days} DAY
        ";
        $result = ClickHouseService::query($sql);
        $row = $result->first();
        return [
            'total' => (string) ($row['total'] ?? '0'),
            'count' => (int) ($row['cnt'] ?? 0),
            'avg'   => (string) ($row['avg_amount'] ?? '0'),
        ];
    }

    /**
     * 按游戏的充值转化率
     * @return array<int, array{game_id: int, players: int, depositors: int, conversion: float}>
     */
    public static function conversionByGame(int $days = 30, int $limit = 10): array
    {
        $sql = "
            SELECT
                pl.game_id,
                uniq(pl.user_id) AS players,
                uniqIf(pl.user_id, dl.user_id > 0) AS depositors,
                depositors / players AS conversion
            FROM erik_game_play_log AS pl
            LEFT JOIN (
                SELECT DISTINCT user_id FROM erik_deposit_log
                WHERE status = 'confirmed'
                  AND created_at >= now() - INTERVAL {$days} DAY
            ) AS dl ON pl.user_id = dl.user_id
            WHERE pl.created_at >= now() - INTERVAL {$days} DAY
            GROUP BY pl.game_id
            ORDER BY conversion DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'game_id'    => (int) $row['game_id'],
            'players'    => (int) $row['players'],
            'depositors' => (int) $row['depositors'],
            'conversion' => round((float) $row['conversion'], 4),
        ], $result->toArray());
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(
                workerId: (int) ($cfg['worker_id'] ?? 1),
                datacenterId: (int) ($cfg['datacenter_id'] ?? 1),
                epoch: $cfg['start_timestamp'] ?? null,
            );
        }
        return self::$snowflake->nextId();
    }
}
