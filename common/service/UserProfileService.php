<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 用户画像服务
 *
 * 基于 ClickHouse 行为数据构建用户标签体系：
 * 活跃度 / 游戏偏好 / 行为模式 / 时段偏好
 */
class UserProfileService
{
    /**
     * 获取用户完整画像
     *
     * @return array{tags: array, metrics: array, preferences: array}
     */
    public static function getProfile(int $userId): array
    {
        $metrics = self::getMetrics($userId);

        return [
            'tags'        => self::buildTags($metrics),
            'metrics'     => $metrics,
            'preferences' => self::getPreferences($userId),
        ];
    }

    /**
     * 用户行为指标
     */
    public static function getMetrics(int $userId): array
    {
        $sql = "
            SELECT
                count() AS total_actions,
                uniq(toDate(created_at)) AS active_days,
                uniq(game_id) AS games_played,
                uniq(ip_address) AS ip_count
            FROM erik_game_play_log
            WHERE user_id = {$userId}
        ";

        $result = ClickHouseService::query($sql);
        $row = $result->first();

        $sql2 = "
            SELECT toHour(created_at) AS hour, count() AS cnt
            FROM erik_game_play_log
            WHERE user_id = {$userId}
            GROUP BY hour ORDER BY cnt DESC LIMIT 1
        ";

        $result2 = ClickHouseService::query($sql2);
        $peak = $result2->first();

        return [
            'total_actions' => (int) ($row['total_actions'] ?? 0),
            'active_days'   => (int) ($row['active_days'] ?? 0),
            'games_played'  => (int) ($row['games_played'] ?? 0),
            'ip_count'      => (int) ($row['ip_count'] ?? 0),
            'peak_hour'     => (int) ($peak['hour'] ?? 0),
        ];
    }

    /**
     * 用户游戏偏好
     */
    public static function getPreferences(int $userId, int $limit = 5): array
    {
        $sql = "
            SELECT game_id, count() AS plays
            FROM erik_game_play_log
            WHERE user_id = {$userId}
            GROUP BY game_id
            ORDER BY plays DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);
        $items = $result->toArray();
        $total = array_sum(array_column($items, 'plays')) ?: 1;

        return array_map(fn(array $row) => [
            'game_id' => (int) $row['game_id'],
            'plays'   => (int) $row['plays'],
            'pct'     => round($row['plays'] / $total * 100, 1),
        ], $items);
    }

    /**
     * 批量获取用户指标
     */
    public static function batchMetrics(array $userIds): array
    {
        if (empty($userIds)) return [];

        $idList = implode(', ', $userIds);
        $sql = "
            SELECT
                user_id,
                count() AS total_actions,
                uniq(game_id) AS games_played,
                uniq(toDate(created_at)) AS active_days
            FROM erik_game_play_log
            WHERE user_id IN ({$idList})
            GROUP BY user_id
        ";

        $result = ClickHouseService::query($sql);
        $out = [];
        foreach ($result->toArray() as $row) {
            $out[(int) $row['user_id']] = [
                'user_id'       => (int) $row['user_id'],
                'games_played'  => (int) $row['games_played'],
                'active_days'   => (int) $row['active_days'],
                'total_actions' => (int) $row['total_actions'],
            ];
        }
        return $out;
    }

    private static function buildTags(array $m): array
    {
        $tags = [];

        $tags[] = match (true) {
            $m['active_days'] >= 20 => 'daily_active',
            $m['active_days'] >= 7  => 'weekly_active',
            $m['active_days'] > 0   => 'casual',
            default                  => 'dormant',
        };

        $tags[] = match (true) {
            $m['games_played'] >= 5 => 'explorer',
            $m['games_played'] >= 2 => 'multi_game',
            default                  => 'focused',
        };

        $tags[] = match (true) {
            $m['total_actions'] > 500 => 'hardcore',
            $m['total_actions'] > 50  => 'regular',
            default                    => 'light',
        };

        $tags[] = $m['ip_count'] <= 1 ? 'stable_ip' : ($m['ip_count'] >= 5 ? 'roaming' : 'normal_ip');

        $tags[] = match (true) {
            $m['peak_hour'] < 6  => 'night_owl',
            $m['peak_hour'] < 12 => 'morning_player',
            $m['peak_hour'] < 18 => 'afternoon_player',
            default              => 'evening_player',
        };

        return $tags;
    }
}
