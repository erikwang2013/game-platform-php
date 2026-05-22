<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids;

/**
 * Webman discovers this class via composer package install hooks ({@see support\Plugin}).
 */
final class Install
{
    public const WEBMAN_PLUGIN = true;

    /**
     * @var array<string, string>
     */
    protected static array $pathRelation = [
        'config/plugin/erikwang2013/hashids' => 'config/plugin/erikwang2013/hashids',
    ];

    public static function install(bool $confirm = false): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            if (str_contains($dest, '..')) {
                continue;
            }
            if (($pos = strrpos($dest, '/')) !== false) {
                $parentDir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }
            }
            copy_dir(__DIR__ . '/' . $source, base_path() . '/' . $dest);
        }

        $hashidsDest = base_path() . '/config/hashids.php';
        $hashidsSrc = dirname(__DIR__) . '/config/hashids.php';
        if (($confirm || !is_file($hashidsDest)) && is_file($hashidsSrc)) {
            $hashidsParent = dirname($hashidsDest);
            if (!is_dir($hashidsParent)) {
                mkdir($hashidsParent, 0755, true);
            }
            copy($hashidsSrc, $hashidsDest);
        }
    }

    public static function uninstall(): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . '/' . $dest;
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            if (is_file($path) || is_link($path)) {
                unlink($path);

                continue;
            }
            remove_dir($path);
        }
    }
}
