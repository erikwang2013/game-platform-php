<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\Webman;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Support\PackagePluginPaths;
use Erikwang2013\Encryptable\Support\PreviousKeysParser;

/**
 * Reads options published under Webman's official plugin config path:
 * {@code config/plugin/erikwang2013/encryptable/app.php} → {@code config('plugin.erikwang2013.encryptable.app.*')}.
 *
 * @see https://webman.workerman.net/doc/en/plugin/create.html
 */
final class WebmanPluginEncryptableConfig implements EncryptableConfigContract
{
    public static function composerPackageName(): string
    {
        return PackagePluginPaths::COMPOSER_NAME;
    }

    /**
     * Dot path prefix for {@code config()}, excluding trailing key (key / cipher).
     */
    public static function configDotPrefix(): string
    {
        [$vendor, $plugin] = PackagePluginPaths::splitVendorPackage();

        return "plugin.{$vendor}.{$plugin}.app";
    }

    /**
     * Relative to project root: {@code config/plugin/.../app.php}.
     */
    public static function appConfigRelativePath(): string
    {
        return PackagePluginPaths::pluginAppConfigRelativePath();
    }

    public static function appConfigAbsolutePath(): ?string
    {
        if (! function_exists('base_path')) {
            return null;
        }

        return base_path(self::appConfigRelativePath());
    }

    public function getKey(): ?string
    {
        $key = config(self::configDotPrefix().'.key');

        if ($key === null || $key === '') {
            return null;
        }

        return (string) $key;
    }

    public function getCipher(): ?string
    {
        $cipher = config(self::configDotPrefix().'.cipher', 'aes-256-gcm');

        if ($cipher === null || $cipher === '') {
            return null;
        }

        return (string) $cipher;
    }

    public function getPreviousKeys(): array
    {
        return PreviousKeysParser::parse(config(self::configDotPrefix().'.previous_keys', []));
    }
}
