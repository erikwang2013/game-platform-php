<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\event\EventBus;
use support\Log;

/**
 * Redis Pub/Sub 消费进程：承接非关键事件（game.played/referral.applied 等）。
 * 与 Outbox 轮询进程（EventConsumer）共用静态 dispatch，派发逻辑不重复实现。
 */
class EventSubscriber
{
    public function onWorkerStart(): void
    {
        Log::info('EventSubscriber started', ['channel' => EventBus::CHANNEL]);

        while (true) {
            try {
                EventBus::subscribe(function (string $message): void {
                    $this->dispatchMessage($message);
                });
            } catch (\Throwable $e) {
                Log::warning('EventSubscriber subscribe interrupted, reconnecting: ' . $e->getMessage());
                sleep(3);
            }
        }
    }

    private function dispatchMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (!is_array($data) || empty($data['event'])) {
                Log::warning('EventSubscriber invalid message', [
                    'raw' => substr($message, 0, 200),
                ]);
                return;
            }

            $event = (string) $data['event'];
            $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

            EventConsumer::dispatch($event, $payload);
        } catch (\Throwable $e) {
            Log::warning('EventSubscriber dispatch failed: ' . $e->getMessage());
        }
    }
}
