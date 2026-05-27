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
            Log::warning('CH deposit_log sync fail: ' . $e->getMessage());
        }
    }

    public static function revenueOverview(int $days = 7): array
    {
        $sql = "SELECT sum(toDecimal64(amount,4)) AS total, count() AS cnt FROM erik_deposit_log WHERE status='confirmed' AND created_at >= now() - INTERVAL {$days} DAY";
        $r = ClickHouseService::query($sql)->first();
        return ['total' => (string)($r['total'] ?? '0'), 'count' => (int)($r['cnt'] ?? 0)];
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(workerId: (int)($cfg['worker_id'] ?? 1), datacenterId: (int)($cfg['datacenter_id'] ?? 1));
        }
        return self::$snowflake->nextId();
    }
}
