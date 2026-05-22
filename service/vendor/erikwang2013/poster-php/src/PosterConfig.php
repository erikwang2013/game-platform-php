<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster;

class PosterConfig
{
    private static ?array $config = null;

    public static function load(?string $path = null): array
    {
        if (self::$config !== null && $path === null) {
            return self::$config;
        }
        $defaultPath = dirname(__DIR__) . '/config/poster.php';
        $path = $path ?? $defaultPath;
        self::$config = is_file($path) ? require $path : require $defaultPath;
        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }
        return $config;
    }

    public static function merge(array $overrides): array
    {
        self::load();
        self::$config = array_replace_recursive(self::$config ?? [], $overrides);
        return self::$config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }
}
