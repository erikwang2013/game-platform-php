<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 反作弊/反机器人检测服务
 *
 * 利用 ClickHouse 时序分析识别异常行为模式。
 */
class AntiCheatService
{
    /**
     * 检测操作间隔过于规律的用户（脚本嫌疑）
     *
     * @return array<int, array{user_id: int, actions: int, avg_interval: float, score: int}>
     */
    public static function detectBotPattern(int $windowMinutes = 30, int $minActions = 20, int $limit = 20): array
    {
        $sql = "
            SELECT
                user_id,
                count() AS actions,
                avg(dateDiff('second', prev_time, created_at)) AS avg_interval
            FROM (
                SELECT
                    user_id, created_at,
                    lagInFrame(created_at) OVER (PARTITION BY user_id ORDER BY created_at) AS prev_time
                FROM erik_game_play_log
                WHERE created_at >= now() - INTERVAL {$windowMinutes} MINUTE
            )
            WHERE prev_time IS NOT NULL
            GROUP BY user_id
            HAVING actions >= {$minActions}
            ORDER BY avg_interval ASC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        $suspects = [];
        foreach ($result->toArray() as $row) {
            $interval = (float) $row['avg_interval'];
            $suspects[] = [
                'user_id'      => (int) $row['user_id'],
                'actions'      => (int) $row['actions'],
                'avg_interval' => round($interval, 2),
                'score'        => match (true) {
                    $interval < 1  => 90,
                    $interval < 3  => 70,
                    $interval < 5  => 50,
                    $interval < 10 => 30,
                    default        => 10,
                },
            ];
        }
        return $suspects;
    }

    /**
     * 检测 24h 不间断活动（多人共用/挂机脚本）
     *
     * @return array<int, array{user_id: int, active_hours: int, total_actions: int}>
     */
    public static function detect24HourActivity(int $hoursBack = 24, int $minHours = 18, int $limit = 20): array
    {
        $sql = "
            SELECT
                user_id,
                uniq(toHour(created_at)) AS active_hours,
                count() AS total_actions
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY user_id
            HAVING active_hours >= {$minHours}
            ORDER BY active_hours DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'user_id'       => (int) $row['user_id'],
            'active_hours'  => (int) $row['active_hours'],
            'total_actions' => (int) $row['total_actions'],
        ], $result->toArray());
    }

    /**
     * 检测极端行为密度
     *
     * @return array<int, array{user_id: int, game_id: int, actions_per_min: float, total: int}>
     */
    public static function detectDensityAnomaly(int $windowMinutes = 10, int $threshold = 100, int $limit = 20): array
    {
        $sql = "
            SELECT
                user_id, game_id,
                count() AS total,
                count() / {$windowMinutes} AS actions_per_min
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$windowMinutes} MINUTE
            GROUP BY user_id, game_id
            HAVING total >= {$threshold}
            ORDER BY total DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'user_id'         => (int) $row['user_id'],
            'game_id'         => (int) $row['game_id'],
            'actions_per_min' => round((float) $row['actions_per_min'], 1),
            'total'           => (int) $row['total'],
        ], $result->toArray());
    }

    /**
     * 检测同一 IP 短时间大量账号切换（注册机/羊毛党）
     *
     * @return array<int, array{ip_address: string, accounts: int, timespan_sec: float}>
     */
    public static function detectAccountFarming(int $windowMinutes = 60, int $minAccounts = 5, int $limit = 20): array
    {
        $sql = "
            SELECT
                ip_address,
                uniq(user_id) AS accounts,
                dateDiff('second', min(created_at), max(created_at)) AS timespan_sec
            FROM erik_game_play_log
            WHERE ip_address != ''
              AND created_at >= now() - INTERVAL {$windowMinutes} MINUTE
            GROUP BY ip_address
            HAVING accounts >= {$minAccounts}
            ORDER BY accounts DESC
            LIMIT {$limit}
        ";
        $result = ClickHouseService::query($sql);
        return array_map(fn(array $row): array => [
            'ip_address'   => $row['ip_address'],
            'accounts'     => (int) $row['accounts'],
            'timespan_sec' => round((float) $row['timespan_sec'], 1),
        ], $result->toArray());
    }

    /**
     * 综合反作弊评分 0-100
     *
     * @return array{score: int, flags: array, details: array}
     */
    public static function assessUser(int $userId): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        $sql = "SELECT uniq(toHour(created_at)) AS active_hours FROM erik_game_play_log WHERE user_id = {$userId} AND created_at >= now() - INTERVAL 24 HOUR";
        $r = ClickHouseService::query($sql);
        $hours = (int) ($r->first()['active_hours'] ?? 0);
        if ($hours >= 20) { $score += 40; $flags[] = 'nonstop_activity'; }
        $details['active_hours_24h'] = $hours;

        $sql2 = "SELECT count() AS actions FROM erik_game_play_log WHERE user_id = {$userId} AND created_at >= now() - INTERVAL 10 MINUTE";
        $r2 = ClickHouseService::query($sql2);
        $actions = (int) ($r2->first()['actions'] ?? 0);
        if ($actions > 100) { $score += 35; $flags[] = 'extreme_density'; }
        $details['actions_10min'] = $actions;

        $sql3 = "SELECT uniq(user_id) AS linked FROM erik_game_play_log WHERE ip_address IN (SELECT DISTINCT ip_address FROM erik_game_play_log WHERE user_id = {$userId} AND ip_address != '') AND created_at >= now() - INTERVAL 24 HOUR";
        $r3 = ClickHouseService::query($sql3);
        $linked = (int) ($r3->first()['linked'] ?? 0);
        if ($linked >= 5) { $score += 25; $flags[] = 'account_farm'; }
        $details['linked_accounts'] = $linked;

        return ['score' => min($score, 100), 'flags' => $flags, 'details' => $details];
    }
}
