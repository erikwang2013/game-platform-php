<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Support;

/**
 * Composer 包名对应的「插件式」配置路径（与 Webman 官方 {@code config/plugin/{vendor}/{name}/} 一致；
 * Hyperf 使用 {@code config/autoload} 下相对路径映射为点号配置键，见 {@see https://github.com/hyperf/hyperf/blob/master/src/config/src/ConfigFactory.php}）。
 */
final class PackagePluginPaths
{
    /** 须与 {@code composer.json} 的 {@code name} 一致。 */
    public const COMPOSER_NAME = 'erikwang2013/encryptable';

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitVendorPackage(): array
    {
        return explode('/', self::COMPOSER_NAME, 2);
    }

    /**
     * 相对项目根：{@code config/plugin/{vendor}/{package}/app.php}（Laravel / Lumen / ThinkPHP / Webman 共用布局）。
     */
    public static function pluginAppConfigRelativePath(): string
    {
        [$vendor, $package] = self::splitVendorPackage();

        return "config/plugin/{$vendor}/{$package}/app.php";
    }

    /**
     * 相对项目根：{@code config/autoload/plugins/{vendor}/{package}.php}（Hyperf 合并后键为 {@code plugins.{vendor}.{package}.*}）。
     */
    public static function hyperfPluginAutoloadRelativePath(): string
    {
        [$vendor, $package] = self::splitVendorPackage();

        return "config/autoload/plugins/{$vendor}/{$package}.php";
    }

    public static function hyperfPluginConfigDotPrefix(): string
    {
        [$vendor, $package] = self::splitVendorPackage();

        return "plugins.{$vendor}.{$package}";
    }
}
