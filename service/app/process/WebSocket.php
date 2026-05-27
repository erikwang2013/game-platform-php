<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use common\service\WebSocketService;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class WebSocket
{
    private static array $connections = [];

    public function onConnect(TcpConnection $connection): void
    {
        self::$connections[$connection->id] = $connection;
    }

    public function onClose(TcpConnection $connection): void
    {
        unset(self::$connections[$connection->id]);
    }

    public function onMessage(TcpConnection $connection, string $data): void
    {
        $msg = json_decode($data, true) ?: [];
        $action = $msg['action'] ?? '';

        match ($action) {
            'leaderboard' => WebSocketService::pushLeaderboard([$connection]),
            'overview'    => WebSocketService::pushOverview([$connection]),
            default       => $connection->send(json_encode([
                'type' => 'help',
                'actions' => ['leaderboard', 'overview'],
            ])),
        };
    }

    public function onWorkerStart(Worker $worker): void
    {
        Timer::add(30, function () {
            if (empty(self::$connections)) return;
            WebSocketService::pushOverview(self::$connections);
            WebSocketService::pushRiskAlert(self::$connections);
            WebSocketService::pushLeaderboard(self::$connections);
        });

        Timer::add(5, function () {
            if (empty(self::$connections)) return;
            WebSocketService::pushGameEvents(self::$connections);
        });
    }
}
