<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\event;

use support\Redis;

class EventBus
{
    const CHANNEL = 'platform:events';

    /**
     * Emit an event to Redis Pub/Sub channel.
     * Subscribers: achievement engine, webhook dispatcher, audit logger.
     */
    public static function emit(string $event, array $payload = []): void
    {
        try {
            $message = json_encode([
                'event' => $event,
                'payload' => $payload,
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE);

            Redis::publish(self::CHANNEL, $message);
        } catch (\Throwable $e) {
            // Event emission failure must not break the main flow
        }
    }

    /**
     * Subscribe to events (used by long-running processes).
     * Returns an iterator of event messages.
     */
    public static function subscribe(): \Closure
    {
        return function () {
            try {
                Redis::subscribe([self::CHANNEL], function ($channel, $message) {
                    return $message;
                });
            } catch (\Throwable $e) {
                // Reconnect handled by caller
            }
        };
    }
}
