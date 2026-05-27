<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 游戏推荐引擎
 *
 * 基于 ClickHouse 中用户行为日志的协同过滤推荐。
 * 依赖：erik_game_play_log 表（由 GamePlayLogService 双写维护）。
 */
class RecommendService
{
    /**
     * 「玩过这个游戏的人也玩了...」协同过滤
     *
     * @return array<int, array{game_id: int, co_players: int, affinity: float}>
     */
    public static function alsoPlayed(int $gameId, int $limit = 10): array
    {
        $sql = "
            SELECT
                pl.game_id,
                uniq(pl.user_id) AS co_players
            FROM erik_game_play_log AS pl
            WHERE pl.user_id IN (
                SELECT DISTINCT user_id FROM erik_game_play_log WHERE game_id = {$gameId}
            )
              AND pl.game_id != {$gameId}
            GROUP BY pl.game_id
            ORDER BY co_players DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);
        $items = $result->toArray();
        $maxPlayers = $items ? max(array_column($items, 'co_players')) : 1;

        return array_map(function (array $row) use ($maxPlayers): array {
            return [
                'game_id'    => (int) $row['game_id'],
                'co_players' => (int) $row['co_players'],
                'affinity'   => $maxPlayers > 0
                    ? round($row['co_players'] / $maxPlayers, 4)
                    : 0,
            ];
        }, $items);
    }

    /**
     * 热门游戏（按近期活跃玩家数排序）
     *
     * @return array<int, array{game_id: int, unique_players: int, total_plays: int}>
     */
    public static function trending(int $hoursBack = 168, int $limit = 10): array
    {
        $interval = (int) ($hoursBack / 24);
        $timeExpr = $hoursBack > 24
            ? "created_at >= now() - INTERVAL {$interval} DAY"
            : "created_at >= now() - INTERVAL {$hoursBack} HOUR";

        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS unique_players,
                count() AS total_plays
            FROM erik_game_play_log
            WHERE {$timeExpr}
            GROUP BY game_id
            ORDER BY total_plays DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'game_id'        => (int) $row['game_id'],
            'unique_players' => (int) $row['unique_players'],
            'total_plays'    => (int) $row['total_plays'],
        ], $result->toArray());
    }

    /**
     * 个性化推荐：基于用户历史，找相似用户玩的游戏
     *
     * @return array<int, array{game_id: int, players: int}>
     */
    public static function forUser(int $userId, int $limit = 10): array
    {
        $sql = "
            SELECT
                game_id,
                uniq(user_id) AS players
            FROM erik_game_play_log
            WHERE user_id IN (
                SELECT DISTINCT user_id
                FROM erik_game_play_log
                WHERE game_id IN (
                    SELECT game_id FROM erik_game_play_log WHERE user_id = {$userId}
                )
                  AND user_id != {$userId}
            )
              AND game_id NOT IN (
                SELECT game_id FROM erik_game_play_log WHERE user_id = {$userId}
              )
            GROUP BY game_id
            ORDER BY players DESC
            LIMIT {$limit}
        ";

        $result = ClickHouseService::query($sql);

        return array_map(fn(array $row): array => [
            'game_id' => (int) $row['game_id'],
            'players' => (int) $row['players'],
        ], $result->toArray());
    }

    /**
     * 游戏关联度（联合概率近似）
     *
     * 所有用户中，同时玩过两个游戏的重合度
     */
    public static function gameAffinity(int $gameIdA, int $gameIdB): float
    {
        $sql = "
            SELECT
                uniq(user_id) AS total_users,
                uniqIf(user_id, game_id = {$gameIdA}) AS users_a,
                uniqIf(user_id, game_id = {$gameIdB}) AS users_b
            FROM (
                SELECT user_id, game_id
                FROM erik_game_play_log
                WHERE game_id IN ({$gameIdA}, {$gameIdB})
                GROUP BY user_id, game_id
            )
        ";

        $result = ClickHouseService::query($sql);
        $row = $result->first();

        if (!$row || (int) ($row['users_a'] ?? 0) === 0) {
            return 0.0;
        }

        $total = (int) ($row['total_users'] ?? 1);
        $a = (int) ($row['users_a'] ?? 0);
        $b = (int) ($row['users_b'] ?? 0);
        $both = min($a, $b);

        return $total > 0 ? round($both / $total, 4) : 0.0;
    }
}
