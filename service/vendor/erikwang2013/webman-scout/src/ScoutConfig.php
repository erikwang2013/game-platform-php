<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout;

/**
 * Resolves the configuration root for Scout across Webman, Laravel, ThinkPHP, and Hyperf.
 *
 * Default: Webman plugin path `plugin.erikwang2013.webman-scout.app`.
 * Override: set env `SCOUT_CONFIG_KEY` to e.g. `scout` and publish the same array under `config/scout.php`.
 */
class ScoutConfig
{
    protected static ?string $resolvedBase = null;

    public static function resetResolvedBase(): void
    {
        static::$resolvedBase = null;
    }

    public static function baseKey(): string
    {
        if (static::$resolvedBase !== null) {
            return static::$resolvedBase;
        }

        $override = getenv('SCOUT_CONFIG_KEY');
        if (is_string($override) && $override !== '') {
            return static::$resolvedBase = rtrim($override, '.');
        }

        if (! function_exists('config')) {
            return static::$resolvedBase = 'plugin.erikwang2013.webman-scout.app';
        }

        try {
            $webman = config('plugin.erikwang2013.webman-scout.app');
            if (is_array($webman)) {
                return static::$resolvedBase = 'plugin.erikwang2013.webman-scout.app';
            }
        } catch (\Throwable $e) {
            //
        }

        foreach (['scout', 'erikwang2013.webman-scout'] as $candidate) {
            try {
                $v = config($candidate);
                if (is_array($v) && (array_key_exists('driver', $v) || array_key_exists('prefix', $v))) {
                    return static::$resolvedBase = $candidate;
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return static::$resolvedBase = 'plugin.erikwang2013.webman-scout.app';
    }

    /**
     * @param  string|null  $relativeKey  Dot key under the scout root (e.g. "driver", "opensearch.host").
     * @return mixed
     */
    public static function get(?string $relativeKey = null, $default = null)
    {
        $base = static::baseKey();

        if ($relativeKey === null || $relativeKey === '') {
            try {
                return config($base, $default);
            } catch (\Throwable $e) {
                return $default;
            }
        }

        try {
            return config($base.'.'.$relativeKey, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
