<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\process;

use Workerman\Connection\TcpConnection;
use support\Log;
use support\Redis;

class ChatWebSocket
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
            case 'auth':
                $token = $msg['token'] ?? '';
                if (empty($token)) { $connection->send(json_encode(['type' => 'error', 'message' => 'Token required'])); return; }
                try {
                    $payload = jwt()->verify($token);
                    $connection->userId = (int) $payload->sub;
                    $connection->send(json_encode(['type' => 'authenticated', 'user_id' => $connection->userId]));
                } catch (\Throwable $e) {
                    $connection->send(json_encode(['type' => 'error', 'message' => 'Invalid token']));
                }
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

    public function onWorkerStart(): void
    {
        \Workerman\Timer::add(1, function () {
            try {
                while (true) {
                    $msg = Redis::brpop(['chat:delivery_queue'], 1);
                    if (!$msg) break;
                    $data = json_decode(is_array($msg) ? ($msg[1] ?? '{}') : '{}', true);
                    if ($data && isset($data['to_user_id'])) {
                        $this->deliverToUser((int) $data['to_user_id'], json_encode($data));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ChatWebSocket brpop failed: ' . $e->getMessage());
            }
        });
    }

    public function deliverToUser(int $userId, string $payload): void
    {
        foreach ($this->connections as $conn) {
            if (($conn->userId ?? 0) === $userId) {
                try {
                    $conn->send($payload);
                } catch (\Throwable $e) {
                    unset($this->connections[$conn->id]);
                }
            }
        }
    }
}
