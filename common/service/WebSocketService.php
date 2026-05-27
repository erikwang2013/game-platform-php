<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;
use Workerman\Connection\TcpConnection;

/**
 * WebSocket 实时推送服务
 *
 * 基于 webman WebSocket 推送实时数据。
 *
 * 服务端配置 (config/process.php):
 *   'websocket' => [
 *       'handler' => \app\process\WebSocket::class,
 *       'listen'  => 'websocket://0.0.0.0:8789',
 *   ],
 */
class WebSocketService
{
    /**
     * 推送实时排行榜
     *
     * @param array<TcpConnection> $connections
     */
    public static function pushLeaderboard(array $connections): int
    {
        $sql = "
            SELECT game_id, uniq(user_id) AS players, count() AS plays
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL 1 HOUR
            GROUP BY game_id
            ORDER BY players DESC
            LIMIT 10
        ";
        $result = ClickHouseService::query($sql);
        return self::broadcast($connections, json_encode([
            'type'       => 'leaderboard',
            'timestamp'  => time(),
            'leaderboard'=> $result->toArray(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 推送风控告警
     *
     * @param array<TcpConnection> $connections
     */
    public static function pushRiskAlert(array $connections): int
    {
        $data = json_encode([
            'type'      => 'risk_alert',
            'timestamp' => time(),
            'alerts'    => [
                'high_frequency' => RiskClickHouseService::detectHighFrequency(5, 30, 5),
                'multi_account'  => RiskClickHouseService::detectMultiAccount(24, 3, 5),
                'ip_hopping'     => RiskClickHouseService::detectIpHopping(1, 3, 5),
            ],
        ], JSON_UNESCAPED_UNICODE);
        return self::broadcast($connections, $data);
    }

    /**
     * 推送游戏实时事件
     *
     * @param array<TcpConnection> $connections
     */
    public static function pushGameEvents(array $connections): int
    {
        $sql = "
            SELECT game_id, action, count() AS recent_events
            FROM erik_game_play_log
            WHERE created_at >= now() - INTERVAL 1 MINUTE
            GROUP BY game_id, action
            ORDER BY recent_events DESC
            LIMIT 20
        ";
        $result = ClickHouseService::query($sql);
        return self::broadcast($connections, json_encode([
            'type'      => 'game_events',
            'timestamp' => time(),
            'events'    => $result->toArray(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 推送概览快照
     *
     * @param array<TcpConnection> $connections
     */
    public static function pushOverview(array $connections): int
    {
        $overview = GameDashboardService::overview(1);
        return self::broadcast($connections, json_encode([
            'type'      => 'overview',
            'timestamp' => time(),
            'overview'  => $overview,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<TcpConnection> $connections
     */
    private static function broadcast(array $connections, string $data): int
    {
        $count = 0;
        foreach ($connections as $conn) {
            if ($conn->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $conn->send($data);
                $count++;
            }
        }
        return $count;
    }
}
