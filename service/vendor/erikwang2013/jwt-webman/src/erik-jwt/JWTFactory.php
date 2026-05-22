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

use Memcached;
use PDO;
use Psr\Log\LoggerInterface;

class JWTFactory
{

    /**
     * 从配置创建 JWT 实例。
     */
    public static function createFromConfig(
        array $config,
        ?LoggerInterface $logger = null,
        array $connections = []
    ): JWT {
        $secretKey = $config['secret_key'] ?? '';
        if (empty($secretKey) || strlen($secretKey) < 16) {
            throw JWTException::configError('Secret key must be at least 16 characters');
        }

        $tokenStorage = self::createTokenStorage($config, $connections);
        $advancedConfig = $config['advanced'] ?? [];
        $retryAttempts = (int)($advancedConfig['retry_attempts'] ?? 3);
        $retryDelay    = (int)($advancedConfig['retry_delay'] ?? 100);

        if ($retryAttempts > 1) {
            $tokenStorage = new RetryTokenStorage($tokenStorage, $retryAttempts, $retryDelay);
        }

        $config['_token_storage'] = $tokenStorage;
        $jwt = new JWT($config, $logger);

        $autoCleanup = $advancedConfig['auto_cleanup'] ?? false;
        if ($autoCleanup) {
            self::setupAutoCleanup($jwt, $advancedConfig);
        }

        return $jwt;
    }

    /**
     * 合并 storage 顶层项到 config，使默认配置中 storage.database / storage.prefix 等生效。
     */
    private static function createTokenStorage(array $config, array $connections): TokenStorageInterface
    {
        $merged = array_merge(
            ['database' => 0, 'prefix' => 'jwt_blacklist:', 'path' => null, 'table_name' => 'jwt_blacklist', 'servers' => []],
            $config['storage'] ?? [],
            $config['storage']['config'] ?? []
        );
        $type = $merged['type'] ?? 'file';

        switch ($type) {
            case 'redis':
                return self::createRedisStorage($merged, $connections);
            case 'database':
                return self::createDatabaseStorage($merged, $connections);
            case 'memcached':
                return self::createMemcachedStorage($merged, $connections);
            case 'file':
            default:
                return self::createFileStorage($merged);
        }
    }

    private static function createRedisStorage(array $config, array $connections): RedisTokenStorage
    {
        $redisResolver = $connections['redis'] ?? null;
        if (!$redisResolver || !is_callable($redisResolver)) {
            throw JWTException::storageError('Redis resolver callable required when storage type is redis');
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new RedisTokenStorage($redisResolver, $prefix);
    }

    private static function createDatabaseStorage(array $config, array $connections): DatabaseTokenStorage
    {
        $pdo = $connections['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw JWTException::storageError('PDO instance required when storage type is database');
        }
        $tableName = $config['table_name'] ?? 'jwt_blacklist';
        return new DatabaseTokenStorage($pdo, $tableName);
    }

    private static function createMemcachedStorage(array $config, array $connections): MemcachedTokenStorage
    {
        $memcached = $connections['memcached'] ?? null;
        if (!$memcached instanceof Memcached) {
            $memcached = new Memcached();
            $servers = $config['servers'] ?? [['127.0.0.1', 11211]];
            $memcached->addServers($servers);
            if (isset($config['options'])) {
                $memcached->setOptions($config['options']);
            }
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new MemcachedTokenStorage($memcached, $prefix);
    }

    private static function createFileStorage(array $config): FileTokenStorage
    {
        $storagePath = $config['path'] ?? null;
        $gcProbability = $config['gc_probability'] ?? 0.1;

        $storage = new FileTokenStorage($storagePath);

        // 设置垃圾回收概率
        if (method_exists($storage, 'setGcProbability')) {
            $storage->setGcProbability($gcProbability);
        }

        return $storage;
    }

    /**
     * 设置自动清理
     */
    private static function setupAutoCleanup(JWT $jwt, array $advancedConfig): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $cleanupInterval = $advancedConfig['cleanup_interval'] ?? 3600;

        register_shutdown_function(function () use ($jwt, $cleanupInterval) {
            static $lastCleanup = 0;
            $now = time();

            if ($now - $lastCleanup >= $cleanupInterval) {
                try {
                    $jwt->cleanup();
                    $lastCleanup = $now;
                } catch (\Exception $e) {
                    error_log("JWT auto cleanup failed: " . $e->getMessage());
                }
            }
        });
    }

}
