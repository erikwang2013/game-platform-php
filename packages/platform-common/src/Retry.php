<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Throwable;

/**
 * 指数退避重试 — 仅重试网络类异常（连接失败/5xx/超时），其余直接抛出。
 */
class Retry
{
    public static function run(callable $fn, int $maxAttempts = 3, int $baseDelayMs = 200): mixed
    {
        $maxAttempts = max(1, min(5, $maxAttempts));
        for ($attempt = 1; ; $attempt++) {
            try {
                return $fn();
            } catch (Throwable $e) {
                if ($attempt >= $maxAttempts || !self::isRetryable($e)) {
                    throw $e;
                }
                usleep($baseDelayMs * 1000 * (2 ** ($attempt - 1))); // 200/400/800ms
            }
        }
    }

    public static function isRetryable(Throwable $e): bool
    {
        if ($e instanceof ConnectException || $e instanceof ServerException) {
            return true;
        }
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'timed out') || str_contains($msg, 'curl error 28');
    }
}
