<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 留存分析服务
 *
 * 基于 ClickHouse 做 D1/D7/D30 队列留存分析。
 */
class RetentionService
{
    /**
     * D1/D7/D30 留存率（按首次行为日期分组）
     *
     * @return array<int, array{cohort_date: string, users: int, d1: float, d7: float, d30: float}>
     */
    public static function cohortRetention(int $daysBack = 30, int $limit = 30): array
    {
        $sql = "
            SELECT
                toDate(first_seen) AS cohort_date,
                uniq(user_id) AS users,
                round(countIf(days_since_first = 1) / users, 4) AS d1,
                round(countIf(days_since_first = 7) / users, 4) AS d7,
                round(countIf(days_since_first = 30) / users, 4) AS d30
            FROM (
                SELECT
                    user_id,
                    min(toDate(created_at)) AS first_seen,
                    dateDiff('day', first_seen, toDate(created_at)) AS days_since_first
                FROM erik_game_play_log
                WHERE created_at >= now() - INTERVAL {$daysBack} DAY
                GROUP BY user_id, toDate(created_at)
            )
            GROUP BY cohort_date
            ORDER BY cohort_date DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'cohort_date' => $row['cohort_date'],
            'users'       => (int) $row['users'],
            'd1'          => round((float) $row['d1'], 4),
            'd7'          => round((float) $row['d7'], 4),
            'd30'         => round((float) $row['d30'], 4),
        ], $result->toArray());
    }

    /**
     * 按游戏的留存对比
     *
     * @return array<int, array{game_id: int, users: int, d1: float, d7: float}>
     */
    public static function retentionByGame(int $daysBack = 30, int $limit = 10): array
    {
        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS users,
                round(countIf(days_since_first = 1) / users, 4) AS d1,
                round(countIf(days_since_first = 7) / users, 4) AS d7
            FROM (
                SELECT
                    game_id,
                    user_id,
                    min(toDate(created_at)) OVER (PARTITION BY user_id, game_id) AS first_seen,
                    dateDiff('day', first_seen, toDate(created_at)) AS days_since_first
                FROM erik_game_play_log
                WHERE created_at >= now() - INTERVAL {$daysBack} DAY
            )
            GROUP BY game_id
            HAVING users >= 10
            ORDER BY d7 DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'game_id' => (int) $row['game_id'],
            'users'   => (int) $row['users'],
            'd1'      => round((float) $row['d1'], 4),
            'd7'      => round((float) $row['d7'], 4),
        ], $result->toArray());
    }

    /**
     * 用户流失率
     */
    public static function churnRate(int $daysBack = 30, int $churnDays = 7): float
    {
        $sql = "
            SELECT
                uniq(user_id) AS active_users,
                uniqIf(user_id, last_seen < now() - INTERVAL {$churnDays} DAY) AS churned_users
            FROM (
                SELECT user_id, max(toDate(created_at)) AS last_seen
                FROM erik_game_play_log
                WHERE created_at >= now() - INTERVAL {$daysBack} DAY
                GROUP BY user_id
            )
        ";
        $result = ClickHouseService::query($sql);
        $row = $result->first();
        $active  = (int) ($row['active_users'] ?? 0);
        $churned = (int) ($row['churned_users'] ?? 0);
        return $active > 0 ? round($churned / $active, 4) : 0;
    }

    /**
     * 按 IP 地域的留存对比（用 IP 前缀近似）
     *
     * @return array<int, array{region: string, users: int, d1: float, d7: float}>
     */
    public static function retentionByRegion(int $daysBack = 30, int $limit = 20): array
    {
        $sql = "
            SELECT
                substring(any(ip_address), 1, 3) AS region,
                uniq(user_id) AS users,
                round(countIf(days_since_first = 1) / uniq(user_id), 4) AS d1,
                round(countIf(days_since_first = 7) / uniq(user_id), 4) AS d7
            FROM (
                SELECT
                    user_id,
                    any(ip_address) AS ip_address,
                    toDate(created_at) AS active_date,
                    min(toDate(created_at)) OVER (PARTITION BY user_id) AS first_seen,
                    dateDiff('day', first_seen, active_date) AS days_since_first
                FROM erik_game_play_log
                WHERE ip_address != ''
                  AND created_at >= now() - INTERVAL {$daysBack} DAY
                GROUP BY user_id, toDate(created_at)
            )
            GROUP BY region
            HAVING users >= 5
            ORDER BY d7 DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'region' => $row['region'],
            'users'  => (int) $row['users'],
            'd1'     => round((float) $row['d1'], 4),
            'd7'     => round((float) $row['d7'], 4),
        ], $result->toArray());
    }
}
