<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\event;

use common\service\OutboxWriter;
use common\SnowflakeService;
use support\Log;
use support\Redis;

class EventBus
{
    const CHANNEL = 'platform:events';
    const METRICS_EMIT_KEY = 'metrics:event_emit_total';
    const METRICS_CONSUME_KEY = 'metrics:event_consume_total';

    /**
     * 关键事件清单：资产变动或外部可见，必须可靠投递（Outbox 表）。
     * 非关键事件（game.played/referral.applied）继续走 Pub/Sub emit()。
     */
    const RELIABLE_EVENTS = [
        'deposit.completed', 'withdraw.applied', 'withdraw.completed',
        'exchange.completed', 'risk.alert', 'wallet.mutated',
    ];

    /**
     * Emit an event to Redis Pub/Sub channel.
     * Subscribers: achievement engine, webhook dispatcher, audit logger.
     *
     * id 在**这里生成一次**再放进消息：N 个订阅者收到的是 Redis 扇出的同一条消息字节，
     * 于是同一个事件对所有订阅者是同一个 id。若改到消费者侧生成，每个订阅者会各得一个
     * 不同的 id，跨订阅者的关联（以及下一批要做的事件去重）就失去依据。
     * 形状：与 push() 的 event_id 同为「不透明非空字符串」——可靠路径的 event_id 由调用方
     * 给业务键（deposit_123 / withdraw_456_1 / wallet.mutated:789），emit() 手里没有业务键，
     * 沿用全仓唯一的 id 生成器（RiskService 生成 risk_<snowflake> 亦用同一个）而不另造前缀格式。
     */
    public static function emit(string $event, array $payload = []): void
    {
        try {
            $message = json_encode([
                'id' => (string) SnowflakeService::generate(),
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
     * 可靠投递：关键事件写入 Outbox 表（event_id 幂等键唯一）。
     * - 调用方已在事务内 → 加入当前事务，业务行与事件行同提交
     * - 调用方不在事务内 → 自动包裹事务
     * 必须把 push() 放在 Db::commit() 之前调用。
     *
     * 插入本身是共享层的 common\service\OutboxWriter——admin 侧经 EventPublisher 注册缝
     * 发布同一个事件时也走它，两棵树只有一份写入实现（列集合/JSON 编码不会漂移）。
     */
    public static function push(string $event, string $eventId, array $payload = []): void
    {
        OutboxWriter::write($event, $eventId, $payload);
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
