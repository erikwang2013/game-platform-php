<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\Retry;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;

/**
 * Retry 指数退避重试测试（纯逻辑，无外部依赖）
 * 覆盖: 可重试异常重试至成功、不可重试异常直抛、maxAttempts 上限 5、超时/curl error 28 判定
 */
class RetryTest extends TestCase
{
    private function connectException(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('GET', 'http://example.com'));
    }

    #[Test]
    public function retriesRetryableUntilSuccess(): void
    {
        $calls = 0;
        $fn = function () use (&$calls) {
            $calls++;
            if ($calls < 3) {
                throw $this->connectException();
            }
            return 'ok';
        };

        $this->assertSame('ok', Retry::run($fn, 3, 1));
        $this->assertSame(3, $calls, '应重试 2 次后成功，总调用 3 次');
    }

    #[Test]
    public function nonRetryableExceptionIsThrownImmediately(): void
    {
        $calls = 0;
        $boom = new \RuntimeException('invalid payload');
        $fn = function () use (&$calls, $boom) {
            $calls++;
            throw $boom;
        };

        try {
            Retry::run($fn, 3, 1);
            $this->fail('应直抛业务异常');
        } catch (\RuntimeException $e) {
            $this->assertSame($boom, $e, '应抛同一个异常实例');
        }
        $this->assertSame(1, $calls, '不可重试异常不应重试');
    }

    #[Test]
    public function maxAttemptsIsCappedAtFive(): void
    {
        $calls = 0;
        $fn = function () use (&$calls) {
            $calls++;
            throw $this->connectException();
        };

        try {
            Retry::run($fn, 10, 1); // 请求 10 次，上限 5
            $this->fail('应抛 ConnectException');
        } catch (ConnectException) {
        }
        $this->assertSame(5, $calls, 'maxAttempts 应被钳制到 5');
    }

    #[Test]
    public function retryableExceptionPropagatesAfterExhaustion(): void
    {
        $calls = 0;
        $fn = function () use (&$calls) {
            $calls++;
            throw new ServerException('502 Bad Gateway', new Request('GET', 'http://example.com'), new \GuzzleHttp\Psr7\Response(502));
        };

        try {
            Retry::run($fn, 2, 1);
            $this->fail('应抛 ServerException');
        } catch (ServerException) {
        }
        $this->assertSame(2, $calls, '重试耗尽后应抛最后一次异常');
    }

    #[Test]
    public function timedOutAndCurlError28AreRetryable(): void
    {
        $cases = ['request timed out', 'curl error 28: Operation timed out'];
        foreach ($cases as $i => $msg) {
            $calls = 0;
            $fn = function () use (&$calls, $msg) {
                $calls++;
                if ($calls === 1) {
                    throw new \RuntimeException($msg);
                }
                return 'ok';
            };
            $this->assertSame('ok', Retry::run($fn, 3, 1), "消息「{$msg}」应视为可重试");
            $this->assertSame(2, $calls, "消息「{$msg}」应重试 1 次");
        }
    }

    #[Test]
    public function maxAttemptsOneRunsExactlyOnce(): void
    {
        $calls = 0;
        $fn = function () use (&$calls) {
            $calls++;
            throw $this->connectException();
        };

        try {
            Retry::run($fn, 1, 1);
            $this->fail('应抛 ConnectException');
        } catch (ConnectException) {
        }
        $this->assertSame(1, $calls);
    }
}
