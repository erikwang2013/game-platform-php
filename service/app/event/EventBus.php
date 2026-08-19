<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\event;

use support\Log;
use support\Redis;

class EventBus
{
    const CHANNEL = 'platform:events';
    const METRICS_EMIT_KEY = 'metrics:event_emit_total';
    const METRICS_CONSUME_KEY = 'metrics:event_consume_total';

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
            self::incr(self::METRICS_EMIT_KEY);
        } catch (\Throwable $e) {
            // Event emission failure must not break the main flow
            Log::warning('EventBus emit failed: ' . $e->getMessage(), [
                'event' => $event,
            ]);
        }
    }

    /**
     * Count a consumed event and optionally run a handler.
     * Used by subscribe() and by a dedicated EventConsumer process.
     */
    public static function consume(string $message, ?callable $handler = null): void
    {
        self::incr(self::METRICS_CONSUME_KEY);
        if ($handler !== null) {
            $handler($message);
        }
    }

    /**
     * Blocking subscribe used by long-running processes.
     * Prefers a dedicated ext-redis connection to avoid pool conflicts
     * with SUBSCRIBE; falls back to support\Redis::subscribe.
     */
    public static function subscribe(?callable $handler = null): void
    {
        $handler = $handler ?? static function (string $message): void {};

        try {
            if (extension_loaded('redis') && class_exists(\Redis::class, false)) {
                $config = config('redis.default', []);
                $redis = new \Redis();
                $host = (string)($config['host'] ?? '127.0.0.1');
                $port = (int)($config['port'] ?? 6379);
                $redis->connect($host, $port, 0.0);
                $password = $config['password'] ?? '';
                if ($password !== '' && $password !== false && $password !== null) {
                    $redis->auth((string)$password);
                }
                $database = (int)($config['database'] ?? 0);
                if ($database > 0) {
                    $redis->select($database);
                }
                $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
                $redis->subscribe([self::CHANNEL], static function ($redis, $channel, $message) use ($handler) {
                    try {
                        self::consume((string)$message, $handler);
                    } catch (\Throwable $e) {
                        Log::warning('EventBus handler failed: ' . $e->getMessage());
                    }
                });
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('EventBus dedicated subscribe failed, falling back: ' . $e->getMessage());
        }

        Redis::subscribe([self::CHANNEL], static function (...$args) use ($handler) {
            // illuminate: ($message, $channel); phpredis-style via facade may vary
            $message = null;
            if (count($args) >= 3 && is_string($args[2] ?? null)) {
                $message = $args[2];
            } elseif (isset($args[0]) && is_string($args[0])) {
                $message = $args[0];
            }
            if ($message !== null && $message !== '') {
                try {
                    self::consume($message, $handler);
                } catch (\Throwable $e) {
                    Log::warning('EventBus handler failed: ' . $e->getMessage());
                }
            }
        });
    }

    private static function incr(string $key): void
    {
        try {
            Redis::incr($key);
        } catch (\Throwable) {
        }
    }
}
