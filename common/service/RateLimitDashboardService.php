<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 限流看板服务
 *
 * 基于 ClickHouse 的行为日志提供 IP 级请求分析和可疑流量识别。
 */
class RateLimitDashboardService
{
    /**
     * IP 请求分布
     *
     * @return array<int, array{ip_address: string, requests: int}>
     */
    public static function topIps(int $hoursBack = 24, int $limit = 20): array
    {
        $sql = "
            SELECT
                ip_address,
                count() AS requests
            FROM erik_game_play_log
            WHERE ip_address != ''
              AND created_at >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY ip_address
            ORDER BY requests DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'ip_address' => $row['ip_address'],
            'requests'   => (int) $row['requests'],
        ], $result->toArray());
    }

    /**
     * 按小时的请求量趋势
     *
     * @return array<int, array{hour: int, requests: int}>
     */
    public static function requestTrend(int $daysBack = 7): array
    {
        $sql = "
            SELECT
                toHour(created_at) AS hour,
                count() AS requests
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$daysBack} DAY
            GROUP BY hour
            ORDER BY hour
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'hour'     => (int) $row['hour'],
            'requests' => (int) $row['requests'],
        ], $result->toArray());
    }

    /**
     * 按 action 的请求分布
     *
     * @return array<int, array{action: string, cnt: int, pct: float}>
     */
    public static function actionBreakdown(int $hoursBack = 24): array
    {
        $sql = "
            SELECT
                action,
                count() AS cnt
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY action
            ORDER BY cnt DESC
        ";

        $result = ClickHouseService::query($sql);
        $items = $result->toArray();
        $total = array_sum(array_column($items, 'cnt')) ?: 1;

        return array_map(fn(array $row) => [
            'action' => $row['action'],
            'cnt'    => (int) $row['cnt'],
            'pct'    => round($row['cnt'] / $total * 100, 2),
        ], $items);
    }

    /**
     * 识别可疑请求来源
     *
     * @return array<int, array{ip_address: string, requests: int, first_seen: string, last_seen: string}>
     */
    public static function suspiciousIps(int $hoursBack = 24, int $threshold = 100, int $limit = 20): array
    {
        $sql = "
            SELECT
                ip_address,
                count() AS requests,
                min(created_at) AS first_seen,
                max(created_at) AS last_seen
            FROM erik_game_play_log
            WHERE ip_address != ''
              AND created_at >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY ip_address
            HAVING requests >= {$threshold}
            ORDER BY requests DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'ip_address' => $row['ip_address'],
            'requests'   => (int) $row['requests'],
            'first_seen' => $row['first_seen'],
            'last_seen'  => $row['last_seen'],
        ], $result->toArray());
    }
}
