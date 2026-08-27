<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use support\Redis;
use Throwable;

/**
 * Redis 熔断器 — 连续网络失败达阈值后快速失败（open），冷却窗口过后半开探测。
 *
 * 状态 key: cb:{key}:failures 连续失败计数 / cb:{key}:opened_at 熔断开启时间戳
 * 仅网络类异常计数（ConnectException/ServerException/超时），业务异常不触发熔断。
 */
class CircuitOpenException extends \RuntimeException
{
}

class CircuitBreaker
{
    public static function call(string $key, callable $fn, array $opts = []): mixed
    {
        $threshold = (int) ($opts['failure_threshold'] ?? 5);
        $openWindow = (int) ($opts['open_window'] ?? 30);
        $failKey = "cb:{$key}:failures";
        $openKey = "cb:{$key}:opened_at";

        $openedAt = self::redis(fn () => Redis::get($openKey));
        if ($openedAt !== false && $openedAt !== null) {
            if (time() - (int) $openedAt < $openWindow) {
                throw new CircuitOpenException("Circuit '{$key}' is open");
            }
            // 冷却已过 → 半开探测：清除状态放行一次
            self::redis(fn () => Redis::del($openKey));
            self::redis(fn () => Redis::del($failKey));
        }

        try {
            $result = $fn();
            self::redis(fn () => Redis::del($failKey)); // 成功 → 重置为 closed
            return $result;
        } catch (Throwable $e) {
            if (Retry::isRetryable($e)) {
                $failures = (int) self::redis(fn () => Redis::incr($failKey));
                if ($failures >= $threshold) {
                    self::redis(fn () => Redis::setex($openKey, $openWindow, (string) time()));
                    self::redis(fn () => Redis::del($failKey));
                }
            }
            throw $e;
        }
    }

    private static function redis(callable $op): mixed
    {
        try {
            return $op();
        } catch (Throwable $e) {
            return false; // Redis 不可用 → fail-open，不阻断业务
        }
    }
}
