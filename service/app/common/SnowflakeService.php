<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Erikwang2013\Snowflake\Snowflake;

/**
 * Snowflake ID 生成服务
 * 用于生成全局唯一 BIGINT 主键，替代数据库自增
 */
class SnowflakeService
{
    private static ?Snowflake $instance = null;

    public static function generate(): int
    {
        if (self::$instance === null) {
            $config = config('snowflake', []);
            self::$instance = new Snowflake(
                workerId: (int)($config['worker_id'] ?? 1),
                datacenterId: (int)($config['datacenter_id'] ?? 1),
                epoch: $config['start_timestamp'] ?? null,
            );
        }
        return self::$instance->nextId();
    }
}
