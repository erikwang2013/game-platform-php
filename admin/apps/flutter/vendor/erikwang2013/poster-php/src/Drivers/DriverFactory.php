<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

class DriverFactory
{
    public static function create(?string $driver = null): ImageDriverInterface
    {
        $driver ??= 'auto';

        if ($driver === 'auto') {
            return self::isImagickAvailable() ? new ImagickDriver() : new GdDriver();
        }

        return $driver === 'imagick' ? new ImagickDriver() : new GdDriver();
    }

    public static function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }
}
