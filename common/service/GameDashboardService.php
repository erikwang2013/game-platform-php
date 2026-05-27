<?php

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

class GameDashboardService
{
    public static function overview(int $days = 1): array
    {
        $sql = "SELECT count() AS plays, uniq(user_id) AS players, uniq(game_id) AS games FROM erik_game_play_log WHERE created_at >= now() - INTERVAL {$days} DAY";
        $r = ClickHouseService::query($sql)->first();
        return ['plays' => (int)($r['plays'] ?? 0), 'players' => (int)($r['players'] ?? 0), 'games' => (int)($r['games'] ?? 0)];
    }

    public static function gameRanking(int $days = 7, int $limit = 20): array
    {
        $sql = "SELECT game_id, uniq(user_id) AS players, count() AS plays FROM erik_game_play_log WHERE created_at >= now() - INTERVAL {$days} DAY GROUP BY game_id ORDER BY players DESC LIMIT {$limit}";
        return array_map(fn($r) => ['game_id' => (int)$r['game_id'], 'players' => (int)$r['players'], 'plays' => (int)$r['plays']], ClickHouseService::query($sql)->toArray());
    }

    public static function dauTrend(int $days = 30): array
    {
        $sql = "SELECT toDate(created_at) AS date, uniq(user_id) AS dau, count() AS plays FROM erik_game_play_log WHERE created_at >= now() - INTERVAL {$days} DAY GROUP BY date ORDER BY date";
        return array_map(fn($r) => ['date' => $r['date'], 'dau' => (int)$r['dau'], 'plays' => (int)$r['plays']], ClickHouseService::query($sql)->toArray());
    }

    public static function hourlyTrend(int $gameId = 0, int $days = 7): array
    {
        $f = $gameId > 0 ? "AND game_id = {$gameId}" : '';
        $sql = "SELECT toHour(created_at) AS hour, count() AS cnt FROM erik_game_play_log WHERE created_at >= now() - INTERVAL {$days} DAY {$f} GROUP BY hour ORDER BY hour";
        return array_map(fn($r) => ['hour' => (int)$r['hour'], 'cnt' => (int)$r['cnt']], ClickHouseService::query($sql)->toArray());
    }

    public static function actionDistribution(int $gameId, int $hours = 24): array
    {
        $sql = "SELECT action, count() AS cnt FROM erik_game_play_log WHERE game_id = {$gameId} AND created_at >= now() - INTERVAL {$hours} HOUR GROUP BY action ORDER BY cnt DESC";
        return array_map(fn($r) => ['action' => $r['action'], 'cnt' => (int)$r['cnt']], ClickHouseService::query($sql)->toArray());
    }
}
