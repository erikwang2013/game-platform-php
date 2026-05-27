<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 游戏数据看板服务
 *
 * 基于 ClickHouse 提供面向运营和游戏开发者的实时数据看板。
 */
class GameDashboardService
{
    /**
     * 概览指标
     *
     * @return array{total_plays: int, unique_players: int, unique_games: int}
     */
    public static function overview(int $days = 1): array
    {
        $sql = "
            SELECT
                count() AS total_plays,
                uniq(user_id) AS unique_players,
                uniq(game_id) AS unique_games
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$days} DAY
        ";

        $result = ClickHouseService::query($sql);
        $row = $result->first();

        return [
            'total_plays'    => (int) ($row['total_plays'] ?? 0),
            'unique_players' => (int) ($row['unique_players'] ?? 0),
            'unique_games'   => (int) ($row['unique_games'] ?? 0),
        ];
    }

    /**
     * 按游戏行为分布
     *
     * @return array<int, array{action: string, cnt: int}>
     */
    public static function actionDistribution(int $gameId, int $hoursBack = 24): array
    {
        $sql = "
            SELECT
                action,
                count() AS cnt
            FROM erik_game_play_log
            WHERE game_id = {$gameId}
              AND created_at >= now() - INTERVAL {$hoursBack} HOUR
            GROUP BY action
            ORDER BY cnt DESC
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'action' => $row['action'],
            'cnt'    => (int) $row['cnt'],
        ], $result->toArray());
    }

    /**
     * 按小时的行为趋势（热力图数据）
     *
     * @return array<int, array{hour: int, cnt: int}>
     */
    public static function hourlyTrend(int $gameId = 0, int $daysBack = 7): array
    {
        $gameFilter = $gameId > 0 ? "AND game_id = {$gameId}" : '';

        $sql = "
            SELECT
                toHour(created_at) AS hour,
                count() AS cnt
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$daysBack} DAY
              {$gameFilter}
            GROUP BY hour
            ORDER BY hour
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'hour' => (int) $row['hour'],
            'cnt'  => (int) $row['cnt'],
        ], $result->toArray());
    }

    /**
     * 游戏排行榜（按活跃玩家数）
     *
     * @return array<int, array{game_id: int, players: int, plays: int, avg_daily: float}>
     */
    public static function gameRanking(int $daysBack = 7, int $limit = 20): array
    {
        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS players,
                count() AS plays,
                count() / {$daysBack} AS avg_daily
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$daysBack} DAY
            GROUP BY game_id
            ORDER BY players DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'game_id'   => (int) $row['game_id'],
            'players'   => (int) $row['players'],
            'plays'     => (int) $row['plays'],
            'avg_daily' => round((float) $row['avg_daily'], 1),
        ], $result->toArray());
    }

    /**
     * 日活跃用户趋势
     *
     * @return array<int, array{date: string, dau: int, plays: int}>
     */
    public static function dauTrend(int $daysBack = 30): array
    {
        $sql = "
            SELECT
                toDate(created_at) AS date,
                uniq(user_id) AS dau,
                count() AS plays
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL {$daysBack} DAY
            GROUP BY date
            ORDER BY date
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'date'  => $row['date'],
            'dau'   => (int) $row['dau'],
            'plays' => (int) $row['plays'],
        ], $result->toArray());
    }
}
