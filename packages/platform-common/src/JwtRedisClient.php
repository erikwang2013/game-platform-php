<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use support\Redis;

/**
 * vendor JWTFactory::createFromConfig() 的 redis resolver 返回值（JWT_STORAGE_TYPE=redis 时启用）。
 *
 * 为什么不直接把 support\Redis::connection() 交出去：它返回 Illuminate 的 PhpRedisConnection，
 * 该对象由 webman 的 RedisManager 按 Context 从连接池取、Context 销毁时还池；而 vendor 的
 * RedisTokenStorage 把 resolver 的返回值缓存在自己的实例属性上，该实例又由 jwt_wrapper()/jwt()
 * 的静态单例长期持有 ⇒ 等于把池中一条连接永久钉在池外，随后与协程复用同一条连接。
 * 走 support\Redis 静态门面则每条命令各自取还连接（与 RedisSequenceClient 同一模式）。
 *
 * 三个方法名与 vendor RedisTokenStorage 实际调用的命令一一对应（ping/setex/exists），
 * 刻意只做原样转发：不能加 (bool)/(int) 转型——"Redis 无应答时返回 false"是它 fail-closed
 * 判据的一部分（exists() 的 false 若被 (int) 成 0，就与"键不存在"无法区分 ⇒ 黑名单静默放行）。
 * 也刻意不提供 close()：vendor 的 reconnect() 用 method_exists($client,'close') 做守卫，
 * 缺这个方法才会走"重新 resolve"分支，而池化连接本来就不该由我们关闭。
 */
final class JwtRedisClient
{
    /**
     * @return bool|string 无参调用时 phpredis 返回 true
     */
    public function ping()
    {
        return Redis::ping();
    }

    /**
     * @return bool false 表示 Redis 侧未写入成功，调用方据此抛错
     */
    public function setex(string $key, int $ttl, string $value)
    {
        return Redis::setex($key, $ttl, $value);
    }

    /**
     * @return int|false false（无应答/链路错误）必须原样返回，不可转型成 0
     */
    public function exists(string $key)
    {
        return Redis::exists($key);
    }
}
