<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

use Exception;

class RedisTokenStorage implements TokenStorageInterface
{
    private $prefix;
    private $redisResolver;
    private $connected = false;
    private $connectionChecked = false;

    public function __construct(callable $redisResolver, string $prefix = 'jwt_blacklist:')
    {
        $this->prefix = $prefix;
        $this->redisResolver = $redisResolver;
    }

    /**
     * 检查Redis连接
     */
    private function checkConnection(): void
    {
        try {
            $this->connected = ($this->redisResolver)()->ping() === true;
        } catch (Exception $e) {
            $this->connected = false;
            throw JWTException::storageError('Redis connection failed: ' . $e->getMessage());
        }
    }

    /**
     * 确保连接正常
     */
    private function ensureConnection(): void
    {
        if (!$this->connectionChecked) {
            $this->checkConnection();
            $this->connectionChecked = true;
        }

        if (!$this->connected) {
            $this->connectionChecked = false;
            $this->checkConnection();
            $this->connectionChecked = true;
        }

        if (!$this->connected) {
            throw JWTException::storageError('Redis is not connected');
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        $this->ensureConnection();
        
        try {
            $now = time();
            $ttl = $expireTime - $now;
            
            if ($ttl <= 0) {
                return true; // 已经过期的令牌不需要加入黑名单
            }

            $key = $this->prefix . $jti;
            $result = ($this->redisResolver)()->setex($key, $ttl, '1');
            
            if ($result === false) {
                throw JWTException::storageError('Failed to blacklist token in Redis');
            }
            
            return $result;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis blacklist operation failed: ' . $e->getMessage());
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        $this->ensureConnection();
        
        try {
            $key = $this->prefix . $jti;
            $exists = ($this->redisResolver)()->exists($key);
            
            // 处理不同版本的Redis exists方法返回值
            if (is_bool($exists)) {
                return $exists;
            }
            
            // Redis >= 5.0.0 返回整数
            return (bool) $exists;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis blacklist check failed: ' . $e->getMessage());
        }
    }

    public function cleanup(): bool
    {
        // Redis会自动过期，不需要手动清理
        return true;
    }

    /**
     * 获取Redis连接状态
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 重新连接Redis
     */
    public function reconnect(): bool
    {
        try {
            $redis = ($this->redisResolver)();
            if (method_exists($redis, 'close')) {
                $redis->close();
            }
            $this->connected = false;
            $this->connectionChecked = false;
            $this->checkConnection();
            $this->connectionChecked = true;
            return $this->connected;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis reconnection failed: ' . $e->getMessage());
        }
    }
}