<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use Redis;
use RuntimeException;

class RedisStorage implements StorageInterface
{
    private Redis $redis;
    private string $prefix;

    public function __construct(?Redis $redis = null)
    {
        if ($redis !== null) {
            $this->redis = $redis;
        } else {
            if (!extension_loaded('redis') || !class_exists('Redis')) {
                throw new RuntimeException('Redis extension is not loaded');
            }
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
        $this->prefix = PosterConfig::get('captcha.redis.prefix', 'poster:captcha:');
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return $this->redis->setex(
            $this->prefix . $key, $ttl,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    public function get(string $key): ?array
    {
        $content = $this->redis->get($this->prefix . $key);
        if ($content === false) {
            return null;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return null;
        }
        return $payload['data'];
    }

    public function del(string $key): bool
    {
        $this->redis->del($this->prefix . $key);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function incrementAttempts(string $key): int
    {
        $content = $this->redis->get($this->prefix . $key);
        if ($content === false) {
            return 0;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return 0;
        }
        $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
        $ttl = max(1, ($payload['expire_at'] ?? time() + 300) - time());
        $this->redis->setex($this->prefix . $key, intval($ttl), json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $payload['attempts'];
    }
}
