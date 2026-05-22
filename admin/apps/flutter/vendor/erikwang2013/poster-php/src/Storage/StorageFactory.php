<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use InvalidArgumentException;

class StorageFactory
{
    public static function create(?string $driver = null): StorageInterface
    {
        $driver = $driver ?? PosterConfig::get('captcha.storage', 'auto');

        if ($driver === 'auto') {
            if (extension_loaded('redis') && class_exists('Redis')) {
                try {
                    return new RedisStorage();
                } catch (\Throwable $e) {
                    // Redis unreachable, fall through to session/file
                }
            }
            if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                return new SessionStorage();
            }
            return new FileStorage();
        }

        return match ($driver) {
            'redis'   => new RedisStorage(),
            'session' => new SessionStorage(),
            'file'    => new FileStorage(),
            default   => throw new InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }
}
