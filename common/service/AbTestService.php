<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * A/B 实验框架
 *
 * 基于哈希的用户分桶 + ClickHouse 指标对比。
 */
class AbTestService
{
    /**
     * 将用户分配到实验变体（哈希分桶）
     *
     * @param string $experiment 实验名称
     * @param array  $variants   ['control' => 50, 'treatment' => 50]
     * @return string 变体名称
     */
    public static function assign(string $experiment, int $userId, array $variants = []): string
    {
        if (empty($variants)) {
            $variants = ['control' => 50, 'treatment' => 50];
        }

        $bucket = abs(crc32($experiment . ':' . $userId)) % 100;
        $cumulative = 0;
        $total = array_sum($variants);

        foreach ($variants as $name => $weight) {
            $cumulative += (int) round(($weight / $total) * 100);
            if ($bucket < $cumulative) {
                return $name;
            }
        }

        return array_key_first($variants);
    }

    /**
     * 生成各变体的基础指标报告
     *
     * @return array<string, array{users: int, actions: int, avg_actions_per_user: float}>
     */
    public static function report(int $daysBack = 7): array
    {
        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS users,
                count() AS actions,
                count() / uniq(user_id) AS avg_actions_per_user
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$daysBack} DAY
            GROUP BY game_id
            ORDER BY users DESC
            LIMIT 10
        ";

        $result = ClickHouseService::query($sql);
        $report = [];

        foreach ($result->toArray() as $row) {
            $report["game_{$row['game_id']}"] = [
                'users'                => (int) $row['users'],
                'actions'              => (int) $row['actions'],
                'avg_actions_per_user' => round((float) $row['avg_actions_per_user'], 2),
            ];
        }

        return $report;
    }

    /**
     * 对比两个时期的行为变化（前后对比分析）
     *
     * @return array{before: int, after: int, change_pct: float}
     */
    public static function comparePeriods(string $beforeStart, string $beforeEnd, string $afterStart, string $afterEnd): array
    {
        $sql = "
            SELECT 'before' AS period, count() AS cnt
            FROM erik_game_play_log
            WHERE created_at BETWEEN '{$beforeStart}' AND '{$beforeEnd}'
            UNION ALL
            SELECT 'after' AS period, count() AS cnt
            FROM erik_game_play_log
            WHERE created_at BETWEEN '{$afterStart}' AND '{$afterEnd}'
        ";

        $result = ClickHouseService::query($sql);
        $rows = $result->toArray();

        $before = (int) ($rows[0]['cnt'] ?? 0);
        $after  = (int) ($rows[1]['cnt'] ?? 0);
        $change = $before > 0 ? round(($after - $before) / $before * 100, 2) : 0;

        return ['before' => $before, 'after' => $after, 'change_pct' => $change];
    }
}
