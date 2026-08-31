<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace common\service;

use app\model\ExchangeRecord;
use app\model\GamePlayLog;
use app\model\Leaderboard;
use support\Redis;

/**
 * 排行榜服务
 * Redis Sorted Set 实现，定时刷新
 */
class LeaderboardService
{
    const CACHE_TTL = 3600;
    const CACHE_KEY_PREFIX = 'leaderboard:';

    /**
     * 获取排行榜数据
     */
    public static function getRanking(int $leaderboardId, int $limit = 100): array
    {
        $key = self::CACHE_KEY_PREFIX . $leaderboardId;

        try {
            $cached = Redis::get($key);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Throwable $e) {}

        return self::computeRanking($leaderboardId, $limit);
    }

    /**
     * 计算排行榜并缓存
     */
    public static function computeRanking(int $leaderboardId, int $limit = 100): array
    {
        $board = Leaderboard::find($leaderboardId);
        if (!$board || $board->status !== 1) {
            return [];
        }

        $rankings = [];
        $now = date('Y-m-d H:i:s');

        switch ($board->type) {
            case 'daily':
                $since = date('Y-m-d', strtotime('-1 day'));
                break;
            case 'weekly':
                $since = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'monthly':
                $since = date('Y-m-d', strtotime('-30 days'));
                break;
            default:
                $since = '2000-01-01'; // all time
        }

        // Compute based on metric
        if ($board->metric === 'earned' || $board->metric === 'spent') {
            $query = ExchangeRecord::where('created_at', '>=', $since);
            if ($board->game_id > 0) {
                $query->where('game_id', $board->game_id);
            }
            if ($board->metric === 'earned') {
                $query->where('direction', 'in');
            } else {
                $query->where('direction', 'out');
            }

            $data = $query->selectRaw('user_id, SUM(platform_amount) as total')
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();

            $rank = 1;
            foreach ($data as $row) {
                $rankings[] = [
                    'rank' => $rank++,
                    'user_id' => $row->user_id,
                    'score' => $row->total,
                ];
            }
        } elseif ($board->metric === 'play_count') {
            $query = GamePlayLog::where('created_at', '>=', $since)
                ->where('action', 'start');
            if ($board->game_id > 0) {
                $query->where('game_id', $board->game_id);
            }

            $data = $query->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();

            $rank = 1;
            foreach ($data as $row) {
                $rankings[] = [
                    'rank' => $rank++,
                    'user_id' => $row->user_id,
                    'score' => (string)$row->total,
                ];
            }
        }

        // Cache
        $key = self::CACHE_KEY_PREFIX . $leaderboardId;
        try {
            Redis::setex($key, self::CACHE_TTL, json_encode($rankings, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {}

        return $rankings;
    }

    /**
     * 清除排行榜缓存
     */
    public static function clearCache(int $leaderboardId): void
    {
        try {
            Redis::del(self::CACHE_KEY_PREFIX . $leaderboardId);
        } catch (\Throwable $e) {}
    }
}
