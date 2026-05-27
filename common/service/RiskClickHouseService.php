<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 风控增强服务（ClickHouse 驱动）
 *
 * 利用 ClickHouse 的聚合能力检测 MySQL 单表查询无法高效识别的异常模式。
 * 配合 RiskService 使用：RiskService 做实时单笔检测，RiskClickHouseService 做批量异常分析。
 */
class RiskClickHouseService
{
    /**
     * 检测高频异常用户（单位时间操作次数超过阈值）
     *
     * @return array<int, array{user_id: int, actions: int, game_id: int}>
     */
    public static function detectHighFrequency(int $windowMinutes = 5, int $threshold = 30, int $limit = 20): array
    {
        $sql = "
            SELECT
                user_id,
                game_id,
                count() AS actions
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$windowMinutes} MINUTE
            GROUP BY user_id, game_id
            HAVING actions > {$threshold}
            ORDER BY actions DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'user_id' => (int) $row['user_id'],
            'game_id' => (int) $row['game_id'],
            'actions' => (int) $row['actions'],
        ], $result->toArray());
    }

    /**
     * 检测同一 IP 关联的多个账号（多账号嫌疑）
     *
     * @return array<int, array{ip_address: string, accounts: int, user_ids: array}>
     */
    public static function detectMultiAccount(int $periodHours = 24, int $threshold = 3, int $limit = 20): array
    {
        $sql = "
            SELECT
                ip_address,
                uniq(user_id) AS accounts,
                groupUniqArray(user_id) AS user_ids
            FROM erik_game_play_log
            WHERE ip_address != ''
              AND created_at >= now() - INTERVAL {$periodHours} HOUR
            GROUP BY ip_address
            HAVING accounts >= {$threshold}
            ORDER BY accounts DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'ip_address' => $row['ip_address'],
            'accounts'   => (int) $row['accounts'],
            'user_ids'   => array_map('intval', $row['user_ids'] ?? []),
        ], $result->toArray());
    }

    /**
     * 检测用户异常 IP 切换（短时间内从多 IP 操作）
     *
     * @return array<int, array{user_id: int, ips: int, ip_list: array}>
     */
    public static function detectIpHopping(int $periodHours = 1, int $threshold = 3, int $limit = 20): array
    {
        $sql = "
            SELECT
                user_id,
                uniq(ip_address) AS ips,
                groupUniqArray(ip_address) AS ip_list
            FROM erik_game_play_log
            WHERE ip_address != ''
              AND created_at >= now() - INTERVAL {$periodHours} HOUR
            GROUP BY user_id
            HAVING ips >= {$threshold}
            ORDER BY ips DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'user_id' => (int) $row['user_id'],
            'ips'     => (int) $row['ips'],
            'ip_list' => $row['ip_list'] ?? [],
        ], $result->toArray());
    }

    /**
     * 按游戏的行为分布（识别可能被刷的游戏）
     *
     * @return array<int, array{game_id: int, action: string, cnt: int, hourly_avg: float}>
     */
    public static function detectAnomalousGames(int $hoursBack = 24, int $limit = 20): array
    {
        $sql = "
            SELECT
                game_id,
                action,
                sum(cnt) AS cnt,
                avg(cnt) AS hourly_avg
            FROM erik_game_play_log_hourly
            WHERE hour >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY game_id, action
            ORDER BY cnt DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'game_id'    => (int) $row['game_id'],
            'action'     => $row['action'],
            'cnt'        => (int) $row['cnt'],
            'hourly_avg' => (float) $row['hourly_avg'],
        ], $result->toArray());
    }

    /**
     * 综合风险评估：返回风险评分 0-100
     *
     * @return array{score: int, flags: string[]}
     */
    public static function assessUser(int $userId): array
    {
        $score = 0;
        $flags = [];

        $sql = "
            SELECT count() AS actions
            FROM erik_game_play_log
            WHERE user_id = {$userId}
              AND created_at >= now() - INTERVAL 5 MINUTE
        ";
        $result = ClickHouseService::query($sql);
        $actions = (int) ($result->first()['actions'] ?? 0);
        if ($actions > 30) {
            $score += 30;
            $flags[] = 'high_frequency';
        } elseif ($actions > 15) {
            $score += 15;
            $flags[] = 'elevated_frequency';
        }

        $sql2 = "
            SELECT uniq(ip_address) AS ips
            FROM erik_game_play_log
            WHERE user_id = {$userId}
              AND created_at >= now() - INTERVAL 1 HOUR
              AND ip_address != ''
        ";
        $result2 = ClickHouseService::query($sql2);
        $ips = (int) ($result2->first()['ips'] ?? 0);
        if ($ips >= 5) {
            $score += 40;
            $flags[] = 'ip_hopping';
        } elseif ($ips >= 3) {
            $score += 20;
            $flags[] = 'multiple_ips';
        }

        return [
            'score' => min($score, 100),
            'flags' => $flags,
        ];
    }
}
