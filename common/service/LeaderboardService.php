<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\model\Leaderboard;
use support\Redis;

/**
 * 排行榜服务
 *
 * 使用 Redis Sorted Set 管理排行榜数据。
 * 支持按周期（daily/weekly/monthly/all）查询排名。
 */
class LeaderboardService
{
    private const CACHE_TTL = 3600;

    /**
     * 生成 Redis 排行榜 key
     */
    private static function getKey(int $boardId, string $period = 'all'): string
    {
        return "leaderboard:{$boardId}:{$period}";
    }

    /**
     * 更新玩家分数
     */
    public static function updateScore(int $boardId, int $userId, float $score): void
    {
        $now = date('Ymd');
        $week = date('oW');
        $month = date('Ym');

        $periods = ['all', "daily:{$now}", "weekly:{$week}", "monthly:{$month}"];
        foreach ($periods as $period) {
            Redis::zAdd(self::getKey($boardId, $period), $score, (string) $userId);
        }
    }

    /**
     * 获取排行
     *
     * @param int    $boardId  排行榜ID
     * @param string $period   周期 (all/daily/weekly/monthly)
     * @param int    $page     页码
     * @param int    $perPage  每页条数
     * @return array{items: array, total: int}
     */
    public static function getRanking(int $boardId, string $period = 'all', int $page = 1, int $perPage = 20): array
    {
        $key = self::getKey($boardId, $period);
        $start = ($page - 1) * $perPage;
        $end = $start + $perPage - 1;

        $total = Redis::zCard($key);

        // 按分数降序
        $members = Redis::zRevRange($key, $start, $end, true);

        $items = [];
        $rank = $start + 1;
        foreach ($members as $userId => $score) {
            $items[] = [
                'rank'   => $rank++,
                'user_id' => (int) $userId,
                'score'  => (float) $score,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 获取玩家当前排名
     */
    public static function getUserRank(int $boardId, int $userId, string $period = 'all'): ?array
    {
        $key = self::getKey($boardId, $period);
        $rank = Redis::zRevRank($key, (string) $userId);

        if ($rank === false) {
            return null;
        }

        $score = Redis::zScore($key, (string) $userId);

        return [
            'rank'  => $rank + 1,
            'score' => (float) $score,
        ];
    }

    /**
     * 清除所有周期缓存（用于强制刷新）
     */
    public static function flush(int $boardId): void
    {
        $keys = Redis::keys("leaderboard:{$boardId}:*");
        foreach ($keys as $key) {
            Redis::del($key);
        }
    }
}
