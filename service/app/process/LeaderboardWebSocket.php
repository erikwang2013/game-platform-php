<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use Workerman\Connection\TcpConnection;
use app\service\LeaderboardService;

class LeaderboardWebSocket
{
    protected array $connections = [];

    public function onConnect(TcpConnection $connection): void
    {
        $this->connections[$connection->id] = $connection;
    }

    public function onMessage(TcpConnection $connection, string $data): void
    {
        $msg = json_decode($data, true);
        if (!$msg) return;

        $action = $msg['action'] ?? '';

        switch ($action) {
            case 'subscribe':
                $leaderboardId = (int)($msg['leaderboard_id'] ?? 0);
                if ($leaderboardId > 0) {
                    $connection->leaderboardId = $leaderboardId;
                    $rankings = LeaderboardService::getRanking($leaderboardId, 100);
                    $connection->send(json_encode([
                        'type' => 'ranking',
                        'leaderboard_id' => $leaderboardId,
                        'rankings' => $rankings,
                    ]));
                }
                break;

            case 'unsubscribe':
                unset($connection->leaderboardId);
                break;

            case 'ping':
                $connection->send(json_encode(['type' => 'pong']));
                break;
        }
    }

    public function onClose(TcpConnection $connection): void
    {
        unset($this->connections[$connection->id]);
    }

    /**
     * Broadcast updated rankings to subscribers
     */
    public function broadcastRanking(int $leaderboardId, array $rankings): void
    {
        foreach ($this->connections as $conn) {
            if (($conn->leaderboardId ?? 0) === $leaderboardId) {
                $conn->send(json_encode([
                    'type' => 'ranking_update',
                    'leaderboard_id' => $leaderboardId,
                    'rankings' => $rankings,
                ]));
            }
        }
    }
}
