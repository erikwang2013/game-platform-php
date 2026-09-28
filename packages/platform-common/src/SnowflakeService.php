<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use Erikwang2013\Snowflake\Resolvers\RedisSequenceResolver;
use Erikwang2013\Snowflake\Snowflake;

/**
 * Snowflake ID 生成服务
 * 用于生成全局唯一 BIGINT 主键，替代数据库自增
 */
class SnowflakeService
{
    /**
     * 序列号计数器的 Redis 键前缀（每毫秒一个键 snowflake:seq:<ms偏移>，随毫秒自行过期）。
     *
     * 故意不按 worker_id/datacenter_id 分片：同库内所有产出 ID 的进程共用一个计数器，
     * 唯一性就只依赖「共用计数器」一件事，而不再依赖各进程的 worker 配置碰巧互不相同。
     * service 与 admin 的 worker_id 不同（2 / 1），共用计数器也不会互相撞号（worker 位不同）。
     */
    private const SEQUENCE_KEY_PREFIX = 'snowflake:seq:';

    private static ?Snowflake $instance = null;

    public static function generate(): int
    {
        if (self::$instance === null) {
            $config = config('snowflake', []);
            self::$instance = new Snowflake(
                workerId: (int)($config['worker_id'] ?? 1),
                datacenterId: (int)($config['datacenter_id'] ?? 1),
                epoch: $config['start_timestamp'] ?? null,
                // 不传 resolver 时 vendor 兜底 SequentialSequenceResolver：seq 是进程私有、
                // 且每毫秒归 0 ⇒ 同一 (worker, datacenter) 的多个常驻进程（config/process.php
                // count=3）系统性重号，而 worker_id 走 env、全进程同值，只解决多机不解决多进程。
                // RedisSequenceResolver 把计数器搬到 Redis 跨进程共享。
                // 降级行为（实测，非推断）：Redis 进程不可达 → phpredis 抛 RedisException，
                // 一路穿到这层；Redis 可达但拒绝写入（键类型不是整数/只读从库/ACL/maxmemory）
                // → 客户端返回 false 而非抛异常，由 RedisSequenceClient::incr() 挡下并抛
                // RuntimeException。两条路都是 fail-closed：拿不到号好过发出重号。
                // 绝不回退到进程私有计数器 —— 那不是降级，是把缺陷恢复回去。
                sequenceResolver: new RedisSequenceResolver(new RedisSequenceClient(), self::SEQUENCE_KEY_PREFIX),
            );
        }
        return self::$instance->nextId();
    }
}
