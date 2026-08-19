<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\api\v1\controller\WebhookController;
use app\event\EventBus;
use app\service\AchievementService;
use support\Log;

/**
 * Long-running Redis Pub/Sub consumer for platform:events.
 * Dispatches to AchievementService and WebhookController.
 */
class EventConsumer
{
    public function onWorkerStart(): void
    {
        Log::info('EventConsumer started', ['channel' => EventBus::CHANNEL]);

        while (true) {
            try {
                EventBus::subscribe(function (string $message): void {
                    $this->dispatch($message);
                });
            } catch (\Throwable $e) {
                Log::warning('EventConsumer subscribe interrupted, reconnecting: ' . $e->getMessage());
                sleep(3);
            }
        }
    }

    private function dispatch(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (!is_array($data) || empty($data['event'])) {
                Log::warning('EventConsumer invalid message', [
                    'raw' => substr($message, 0, 200),
                ]);
                return;
            }

            $event = (string) $data['event'];
            $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

            try {
                AchievementService::handle($event, $payload);
            } catch (\Throwable $e) {
                Log::warning('EventConsumer achievement failed: ' . $e->getMessage(), [
                    'event' => $event,
                ]);
            }

            try {
                WebhookController::dispatch($event, $payload);
            } catch (\Throwable $e) {
                Log::warning('EventConsumer webhook failed: ' . $e->getMessage(), [
                    'event' => $event,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('EventConsumer dispatch failed: ' . $e->getMessage());
        }
    }
}
