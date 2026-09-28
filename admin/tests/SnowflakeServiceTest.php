<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\RedisSequenceClient;
use common\SnowflakeService;

class SnowflakeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function generate_returns_positive_integer(): void
    {
        $id = SnowflakeService::generate();
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function generate_id_fits_bigint_range(): void
    {
        $id = SnowflakeService::generate();
        $this->assertLessThanOrEqual(9223372036854775807, $id); // BIGINT max
    }

    #[Test]
    public function generate_produces_unique_ids(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = SnowflakeService::generate();
        }
        $unique = array_unique($ids);
        $this->assertCount(100, $unique, '连续生成 100 个 ID 应全部唯一');
    }

    #[Test]
    public function generate_ids_monotonically_increase(): void
    {
        $prev = SnowflakeService::generate();
        usleep(1000); // 1ms 确保不同毫秒
        $next = SnowflakeService::generate();
        $this->assertGreaterThan($prev, $next, '后生成的 ID 应大于先生成的');
    }

    #[Test]
    public function generate_uses_singleton(): void
    {
        $id1 = SnowflakeService::generate();
        $id2 = SnowflakeService::generate();
        // 如果单例反复初始化会导致 worker_id 漂移
        // 两次调用都应返回有效 ID
        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
    }

    /**
     * 跨进程唯一性完全押在「同库内所有产出 ID 的进程共用一个 Redis 计数器」上。
     * 若退回 vendor 兜底的 SequentialSequenceResolver（进程私有、每毫秒归 0），
     * 这里一个 snowflake:seq:* 键都不会出现——多进程常驻下即系统性重号。
     */
    #[Test]
    public function generate_draws_sequence_from_shared_redis_counter(): void
    {
        foreach ((array) \support\Redis::keys('snowflake:seq:*') as $k) {
            \support\Redis::del($k);
        }

        SnowflakeService::generate();

        $this->assertNotEmpty(
            \support\Redis::keys('snowflake:seq:*'),
            'SnowflakeService 必须把序列计数器放进 Redis 跨进程共享，而不是留在进程私有变量里'
        );
    }

    /**
     * RedisSequenceResolver 用 method_exists($client, 'incr'|'expire') 做守卫，
     * 而 support\Redis::connection() 返回的 PhpRedisConnection 只有 __call 魔术方法——
     * 魔术方法不算数，直接把连接对象交进去会每次抛 InvalidArgumentException。
     */
    #[Test]
    public function sequence_client_satisfies_resolver_method_exists_guard(): void
    {
        $client = new RedisSequenceClient();
        $this->assertTrue(method_exists($client, 'incr'), '必须暴露真实 incr()，不能只靠 __call');
        $this->assertTrue(method_exists($client, 'expire'), '必须暴露真实 expire()，不能只靠 __call');
    }

    /**
     * 回归：INCR 被 Redis 拒绝时 phpredis 返回 false 而不抛异常。若把 false 原样交给
     * resolver，会算出 seq = 0 - 1 = -1，Snowflake::id() 再 `| seq` 把 -1 的全 1 位并进去
     * ⇒ 吐出恒定 ID -1（每个进程、每次调用都一样），比"偶尔重号"更糟。必须 fail-closed。
     */
    #[Test]
    public function sequence_client_refuses_write_rejected_by_redis(): void
    {
        $key = 'snowflake:seq:test:not-an-integer';
        \support\Redis::setex($key, 30, 'not-a-number');

        try {
            (new RedisSequenceClient())->incr($key);
            $this->fail('Redis 拒绝写入时必须抛异常，不得返回 false 让上层算出 seq=-1');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($key, $e->getMessage());
        } finally {
            \support\Redis::del($key);
        }
    }
}
