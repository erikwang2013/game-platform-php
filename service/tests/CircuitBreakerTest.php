<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\CircuitBreaker;
use common\CircuitOpenException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use support\Redis;

/**
 * CircuitBreaker 熔断器测试（真实 Redis，key 使用 cb:test:* 命名空间）
 * 覆盖: 阈值触发 open、成功重置、半开探测恢复、业务异常不计入、自定义阈值
 */
class CircuitBreakerTest extends TestCase
{
    private const KEY = 'test:cb';
    private array $keys = [];

    protected function setUp(): void
    {
        try {
            Redis::setex('cb:probe:alive', 5, '1');
            Redis::del('cb:probe:alive');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
        $this->keys = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) {
            try {
                Redis::del("cb:{$key}:failures");
                Redis::del("cb:{$key}:opened_at");
            } catch (\Throwable) {
                // 忽略清理失败
            }
        }
    }

    private function retryable(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('GET', 'http://example.com'));
    }

    private function register(string $key): string
    {
        $this->keys[] = $key;
        return $key;
    }

    #[Test]
    public function openAfterThresholdThrowsCircuitOpen(): void
    {
        $key = $this->register(self::KEY . ':open');
        $attempts = 0;
        $fn = function () use (&$attempts) {
            $attempts++;
            throw $this->retryable();
        };

        for ($i = 1; $i <= 3; $i++) {
            try {
                CircuitBreaker::call($key, $fn, ['failure_threshold' => 3]);
                $this->fail("第 {$i} 次调用应抛出异常");
            } catch (ConnectException) {
                $this->assertSame($i, $attempts);
            }
        }

        // 第 4 次：熔断已 open → 快速失败
        $this->expectException(CircuitOpenException::class);
        CircuitBreaker::call($key, $fn, ['failure_threshold' => 3]);
    }

    #[Test]
    public function successResetsFailureCount(): void
    {
        $key = $this->register(self::KEY . ':reset');
        $state = 'fail';
        $fn = function () use (&$state) {
            if ($state === 'fail') {
                throw $this->retryable();
            }
            return 'ok';
        };

        // 失败 → 成功 → 失败 → 成功：计数每次成功都重置，永不达阈值 2
        for ($i = 0; $i < 2; $i++) {
            try {
                CircuitBreaker::call($key, $fn);
                $this->fail('应抛 ConnectException');
            } catch (ConnectException) {
            }
            $state = 'ok';
            $this->assertSame('ok', CircuitBreaker::call($key, $fn));
            $state = 'fail';
        }

        $this->assertEmpty(Redis::get("cb:{$key}:failures"), '成功后失败计数应被清除');
        $this->assertEmpty(Redis::get("cb:{$key}:opened_at"), '不应处于 open 状态');
    }

    #[Test]
    public function halfOpenProbeRecoversAfterCooldown(): void
    {
        $key = $this->register(self::KEY . ':halfopen');
        $fail = function () {
            throw new ServerException('500 Internal Server Error', new Request('GET', 'http://example.com'), new \GuzzleHttp\Psr7\Response(500));
        };

        // 阈值 2 触发 open
        for ($i = 0; $i < 2; $i++) {
            try {
                CircuitBreaker::call($key, $fail, ['failure_threshold' => 2, 'open_window' => 1]);
                $this->fail('应抛 ServerException');
            } catch (ServerException) {
            }
        }

        try {
            CircuitBreaker::call($key, $fail, ['failure_threshold' => 2, 'open_window' => 1]);
            $this->fail('open 期间应抛 CircuitOpenException');
        } catch (CircuitOpenException) {
        }

        // 冷却 1s 过后 → 半开探测，成功调用恢复 closed
        usleep(1_100_000);
        $this->assertSame('ok', CircuitBreaker::call($key, fn () => 'ok', ['failure_threshold' => 2, 'open_window' => 1]));
        $this->assertEmpty(Redis::get("cb:{$key}:failures"), '半开探测成功后计数应清除');
        $this->assertEmpty(Redis::get("cb:{$key}:opened_at"), '半开探测成功后 opened_at 应清除');
    }

    #[Test]
    public function businessExceptionDoesNotTripBreaker(): void
    {
        $key = $this->register(self::KEY . ':biz');
        $fn = function () {
            throw new \RuntimeException('invalid amount: negative');
        };

        for ($i = 0; $i < 3; $i++) {
            try {
                CircuitBreaker::call($key, $fn, ['failure_threshold' => 2]);
                $this->fail('应抛业务异常');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('invalid amount', $e->getMessage());
            }
        }
        // 业务异常 3 次也未触发熔断（阈值 2 无效于业务异常），计数 key 不存在
        $this->assertEmpty(Redis::get("cb:{$key}:failures"), '业务异常不应写入失败计数');
        $this->assertEmpty(Redis::get("cb:{$key}:opened_at"), '业务异常不应触发 open');
    }

    #[Test]
    public function customThresholdIsRespected(): void
    {
        $key = $this->register(self::KEY . ':threshold');
        $fn = function () {
            throw $this->retryable();
        };

        // 阈值 4：前 3 次失败后电路仍关闭，第 4 次调用仍进入 fn（达阈值后 open）
        for ($i = 1; $i <= 3; $i++) {
            try {
                CircuitBreaker::call($key, $fn, ['failure_threshold' => 4]);
                $this->fail("第 {$i} 次应抛 ConnectException");
            } catch (ConnectException) {
            }
        }
        try {
            CircuitBreaker::call($key, $fn, ['failure_threshold' => 4]);
            $this->fail('第 4 次应抛 ConnectException 并触发 open');
        } catch (ConnectException) {
        }

        $this->expectException(CircuitOpenException::class);
        CircuitBreaker::call($key, $fn, ['failure_threshold' => 4]);
    }
}
