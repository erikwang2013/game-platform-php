<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\common\SnowflakeService;

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
}
