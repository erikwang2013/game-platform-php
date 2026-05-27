<?php

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;
use Erikwang2013\Snowflake\Snowflake;
use support\Log;

class DepositLogService
{
    private static ?Snowflake $snowflake = null;

    public static function log(int $orderId, int $userId, string $amount, string $currency, string $status, string $method = ''): void
    {
        try {
            ClickHouseService::table('erik_deposit_log')->insert([[
                'id' => self::snowflakeId(), 'order_id' => $orderId, 'user_id' => $userId,
                'amount' => $amount, 'currency' => $currency, 'status' => $status,
                'payment_method' => $method, 'created_at' => date('Y-m-d H:i:s'),
            ]]);
        } catch (\Throwable $e) {
            Log::warning('CH deposit sync fail: ' . $e->getMessage());
        }
    }

    public static function revenueOverview(int $days = 7): array
    {
        $sql = "SELECT sum(toDecimal64(amount,4)) AS total, count() AS cnt, avg(toDecimal64(amount,4)) AS avg_amount FROM erik_deposit_log WHERE status='confirmed' AND created_at >= now() - INTERVAL {$days} DAY";
        $r = ClickHouseService::query($sql)->first();
        return ['total' => (string)($r['total'] ?? '0'), 'count' => (int)($r['cnt'] ?? 0), 'avg' => (string)($r['avg_amount'] ?? '0')];
    }

    public static function conversionByGame(int $days = 30, int $limit = 10): array
    {
        $sql = "SELECT pl.game_id, uniq(pl.user_id) AS players, uniqIf(pl.user_id, dl.user_id>0) AS depositors, depositors/players AS conversion FROM erik_game_play_log AS pl LEFT JOIN (SELECT DISTINCT user_id FROM erik_deposit_log WHERE status='confirmed' AND created_at >= now() - INTERVAL {$days} DAY) AS dl ON pl.user_id=dl.user_id WHERE pl.created_at >= now() - INTERVAL {$days} DAY GROUP BY pl.game_id ORDER BY conversion DESC LIMIT {$limit}";
        return array_map(fn($r) => ['game_id' => (int)$r['game_id'], 'players' => (int)$r['players'], 'depositors' => (int)$r['depositors'], 'conversion' => round((float)$r['conversion'], 4)], ClickHouseService::query($sql)->toArray());
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(workerId: (int)($cfg['worker_id'] ?? 1), datacenterId: (int)($cfg['datacenter_id'] ?? 1), epoch: $cfg['start_timestamp'] ?? null);
        }
        return self::$snowflake->nextId();
    }
}
