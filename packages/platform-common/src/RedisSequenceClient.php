<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use support\Redis;

/**
 * Snowflake 序列号计数器的 Redis 客户端，供 vendor 的 RedisSequenceResolver 使用。
 *
 * 为什么不直接把 support\Redis::connection() 交给 RedisSequenceResolver：
 * 它返回的是 Illuminate\Redis\Connections\PhpRedisConnection，命令经 __call 魔术方法转发，
 * 而 RedisSequenceResolver 的守卫是 method_exists($client, 'incr') —— 魔术方法不算数，
 * 于是每次取值都抛 InvalidArgumentException。必须给出带真实 incr()/expire() 方法的对象。
 *
 * 走 support\Redis 静态门面（而不是 ->client() 取裸 phpredis）：门面按次从连接池取还连接，
 * 而本对象由 SnowflakeService 作为进程内单例长期持有，取裸客户端会把池中一条连接永久钉在池外，
 * 与后续协程复用同一条连接。裸 phpredis 客户端只是恰好也是 method_exists 命中的候选，不是约定。
 */
final class RedisSequenceClient
{
    /**
     * @throws \RuntimeException Redis 侧拒绝写入时抛（fail-closed）
     */
    public function incr(string $key): int
    {
        $counter = Redis::incr($key);

        // 必须挡住 false：phpredis 对「服务端错误」（键类型不是整数、只读从库、ACL、
        // maxmemory 拒绝写入等）返回 false 而**不抛异常**，仅置 getLastError。
        // false 落到 RedisSequenceResolver 会算出 seq = counter - 1 = -1，
        // Snowflake::id() 再做 `| seq`，-1 是全 1 位 ⇒ 直接吐出常量 ID -1，次次相同。
        // 那不是 fail-closed 而是 fail-open，且比原缺陷更糟（从"偶尔重号"变成"恒定同一个号"）。
        if (!is_int($counter)) {
            throw new \RuntimeException(
                "Snowflake 序列计数器 INCR 被 Redis 拒绝：{$key}（Redis 返回 "
                . get_debug_type($counter) . '），拒绝发号以免重号'
            );
        }

        return $counter;
    }

    /**
     * 给毫秒键挂 TTL（仅该键首次 INCR 时调用）。
     *
     * 刻意**不**像 incr() 那样对失败抛异常：挂不上 TTL 只是让这个键活过它自己那一毫秒，
     * 而 vendor 明确说明「键比毫秒活得久不会破坏唯一性」（计数器只增不减、不会在毫秒中途归零），
     * 代价仅是键空间不回收。为此把一次成功的取号判成失败会平白放大故障面。
     */
    public function expire(string $key, int $seconds): bool
    {
        // ponytail: (bool) 仅为统一客户端返回形态（phpredis 给 bool、predis 给 1/0），不参与金额运算。
        return (bool) Redis::expire($key, $seconds);
    }
}
