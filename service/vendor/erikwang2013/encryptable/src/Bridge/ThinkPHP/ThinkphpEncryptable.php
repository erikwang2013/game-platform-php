<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\ThinkPHP;

use Erikwang2013\Encryptable\Contracts\DbDriverDetector;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\DBEncrypter;
use Erikwang2013\Encryptable\Encryption;
use Erikwang2013\Encryptable\PHPEncrypter;
use Erikwang2013\Encryptable\Support\PackagePluginPaths;
use Psr\Container\ContainerInterface;
use think\App;

class ThinkphpEncryptable
{
    public static function register(App $app): void
    {
        self::bootstrapEncryptableConfig($app);

        $app->singleton(EncryptableConfigContract::class, ThinkEncryptableConfig::class);
        $app->singleton(DbDriverDetector::class, ThinkDbDriverDetector::class);

        $app->singleton(PHPEncrypter::class, function () use ($app) {
            return new PHPEncrypter(
                $app->make(EncryptableConfigContract::class)
            );
        });

        $app->singleton(DBEncrypter::class, function () use ($app) {
            return new DBEncrypter(
                $app->make(EncryptableConfigContract::class),
                $app->make(DbDriverDetector::class)
            );
        });

        $psr = $app instanceof ContainerInterface
            ? $app
            : new ThinkPsrContainerAdapter($app);

        Encryption::setContainer($psr);
    }

    /**
     * ThinkPHP 不会自动合并 {@code config/plugin/} 下文件；若存在则注入到 {@code encryptable.*}。
     */
    private static function bootstrapEncryptableConfig(App $app): void
    {
        if (! function_exists('root_path')) {
            return;
        }

        [$vendor, $package] = PackagePluginPaths::splitVendorPackage();
        $plugin = root_path('config/plugin/'.$vendor.'/'.$package.'/app.php');
        if (! is_file($plugin)) {
            return;
        }

        /** @var mixed $data */
        $data = include $plugin;
        if (! is_array($data)) {
            return;
        }

        $app->config->set($data, 'encryptable');
    }
}
