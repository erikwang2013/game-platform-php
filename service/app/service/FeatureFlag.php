<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\model\PlatformConfig;

class FeatureFlag
{
    const NAMESPACE = 'feature';

    public static function isEnabled(string $feature): bool
    {
        $value = PlatformConfig::get(self::NAMESPACE, $feature, 'off');
        return $value === 'on' || $value === '1' || $value === 'true';
    }

    public static function enable(string $feature): void
    {
        PlatformConfig::set(self::NAMESPACE, $feature, 'on');
    }

    public static function disable(string $feature): void
    {
        PlatformConfig::set(self::NAMESPACE, $feature, 'off');
    }

    public static function all(): array
    {
        $configs = PlatformConfig::where('group', self::NAMESPACE)->pluck('value', 'key');
        $flags = [];
        foreach ($configs as $key => $value) {
            $flags[$key] = $value === 'on' || $value === '1' || $value === 'true';
        }
        return $flags;
    }
}
