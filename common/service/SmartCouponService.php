<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 智能优惠券服务
 *
 * 基于 ClickHouse 行为数据，计算最优优惠券策略：
 * - 用户活跃度分析
 * - 流失风险检测
 * - 挽留优惠券建议
 */
class SmartCouponService
{
    /**
     * 获取用户活跃度画像
     *
     * @return array{total_actions: int, active_days: int, games_played: int}
     */
    public static function userActivityProfile(int $userId): array
    {
        $sql = "
            SELECT
                count() AS total_actions,
                uniq(toDate(created_at)) AS active_days,
                uniq(game_id) AS games_played
            FROM erik_game_play_log
            WHERE user_id = {$userId}
        ";

        $result = ClickHouseService::query($sql);
        $row = $result->first();

        return [
            'total_actions' => (int) ($row['total_actions'] ?? 0),
            'active_days'   => (int) ($row['active_days'] ?? 0),
            'games_played'  => (int) ($row['games_played'] ?? 0),
        ];
    }

    /**
     * 检测有流失风险的用户（N 天无行为记录）
     *
     * @return array<int, array{user_id: int, last_seen: string, days_inactive: int}>
     */
    public static function detectChurnRisk(int $daysInactive = 7, int $limit = 50): array
    {
        $sql = "
            SELECT
                user_id,
                max(created_at) AS last_seen,
                dateDiff('day', max(created_at), now()) AS days_inactive
            FROM erik_game_play_log
            GROUP BY user_id
            HAVING days_inactive >= {$daysInactive}
            ORDER BY days_inactive DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'user_id'       => (int) $row['user_id'],
            'last_seen'     => $row['last_seen'],
            'days_inactive' => (int) $row['days_inactive'],
        ], $result->toArray());
    }

    /**
     * 为流失风险用户生成挽留优惠券建议
     *
     * @return array<int, array{user_id: int, days_inactive: int, suggested_amount: string, priority: string}>
     */
    public static function retentionRecommendations(int $daysInactive = 7, int $limit = 20): array
    {
        $atRisk = self::detectChurnRisk($daysInactive, $limit);

        return array_map(function (array $row): array {
            return [
                'user_id'          => $row['user_id'],
                'days_inactive'    => $row['days_inactive'],
                'suggested_amount' => match (true) {
                    $row['days_inactive'] >= 30 => '20.00',
                    $row['days_inactive'] >= 14 => '10.00',
                    default => '5.00',
                },
                'priority' => match (true) {
                    $row['days_inactive'] >= 30 => 'critical',
                    $row['days_inactive'] >= 14 => 'high',
                    default => 'medium',
                },
            ];
        }, $atRisk);
    }

    /**
     * 按游戏统计活跃用户数（用于判断哪个游戏最需要营销）
     *
     * @return array<int, array{game_id: int, players: int, score: float}>
     */
    public static function gameEngagement(int $limit = 10): array
    {
        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS players
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL 7 DAY
            GROUP BY game_id
            ORDER BY players DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);
        $items = $result->toArray();
        $max = $items ? max(array_column($items, 'players')) : 1;

        return array_map(fn(array $row) => [
            'game_id' => (int) $row['game_id'],
            'players' => (int) $row['players'],
            'score'   => $max > 0 ? round($row['players'] / $max, 4) : 0,
        ], $items);
    }
}
