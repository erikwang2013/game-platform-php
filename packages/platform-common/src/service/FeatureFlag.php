<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\model\PlatformConfig;

class FeatureFlag
{
    const NAMESPACE = 'feature';

    public static function isEnabled(string $feature): bool
    {
        $value = PlatformConfig::get(self::NAMESPACE, $feature, 'off');
        return $value === 'on' || $value === '1' || $value === true || $value === 'true';
    }

    /**
     * Percentage rollout (stable crc32 bucket).
     * - feature.{name}=on → 100%
     * - otherwise reads feature.{name}_percent (0–100, default 0)
     */
    public static function inRollout(string $feature, string|int $subjectId, ?int $percent = null): bool
    {
        if (self::isEnabled($feature)) {
            return true;
        }
        if ($percent === null) {
            $percent = (int) PlatformConfig::get(self::NAMESPACE, $feature . '_percent', 0);
        }
        $percent = max(0, min(100, $percent));
        if ($percent <= 0) {
            return false;
        }
        if ($percent >= 100) {
            return true;
        }
        $bucket = ((int) sprintf('%u', crc32($feature . ':' . (string) $subjectId))) % 100;
        return $bucket < $percent;
    }

    /** @see inRollout() */
    public static function abTest(string $experiment, string|int $subjectId, ?int $percent = null): bool
    {
        return self::inRollout($experiment, $subjectId, $percent);
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
