<?php

declare(strict_types=1);

namespace Erikwang2013\Season;

/**
 * Webman 插件安装/卸载
 */
class Install
{
    public const WEBMAN_PLUGIN = true;

    /** @var array 安装时复制/链接的路径对应 */
    protected static array $pathRelation = [
        'config/plugin/erikwang2013/season' => 'config/plugin/erikwang2013/season',
    ];

    public static function install(): void
    {
        static::installByRelation();
    }

    public static function uninstall(): void
    {
        static::uninstallByRelation();
    }

    protected static function installByRelation(): void
    {
        $base = base_path();
        foreach (static::$pathRelation as $source => $dest) {
            $sourceDir = __DIR__ . '/' . $source;
            if (!\is_dir($sourceDir)) {
                continue;
            }
            $destFull = $base . '/' . $dest;
            $parent = \dirname($destFull);
            if (!\is_dir($parent)) {
                \mkdir($parent, 0755, true);
            }
            copy_dir($sourceDir, $destFull);
        }
    }

    protected static function uninstallByRelation(): void
    {
        $base = base_path();
        foreach (static::$pathRelation as $_source => $dest) {
            $path = $base . '/' . $dest;
            if (\is_dir($path) || \is_file($path)) {
                remove_dir($path);
            }
        }
    }
}
