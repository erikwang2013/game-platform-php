<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Illuminate\Support\ServiceProvider;
use Erikwang2013\Encryptable\Bridge\Laravel\IlluminateDbDriverDetector;
use Erikwang2013\Encryptable\Bridge\Laravel\IlluminateEncryptableConfig;
use Erikwang2013\Encryptable\Bridge\Webman\WebmanPluginEncryptableConfig;
use Erikwang2013\Encryptable\Contracts\DbDriverDetector;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Rules\ExistsEncrypted;
use Erikwang2013\Encryptable\Rules\UniqueEncrypted;
use Erikwang2013\Encryptable\Support\PackagePluginPaths;

class EncryptableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->usesWebmanNativePluginConfig()) {
            $this->mergeConfigFrom(
                $this->resolveIlluminateStyleEncryptableConfigPath(),
                'encryptable'
            );
        }

        $this->app->singleton(EncryptableConfigContract::class, function () {
            if ($this->usesWebmanNativePluginConfig()) {
                return new WebmanPluginEncryptableConfig;
            }

            return new IlluminateEncryptableConfig;
        });

        $this->app->singleton(DbDriverDetector::class, function () {
            return new IlluminateDbDriverDetector;
        });

        $this->app->singleton(PHPEncrypter::class, function ($app) {
            return new PHPEncrypter(
                $app->make(EncryptableConfigContract::class)
            );
        });

        $this->app->singleton(DBEncrypter::class, function ($app) {
            return new DBEncrypter(
                $app->make(EncryptableConfigContract::class),
                $app->make(DbDriverDetector::class)
            );
        });
    }

    public function boot(): void
    {
        [$vendor, $package] = PackagePluginPaths::splitVendorPackage();

        $this->publishes([
            dirname(__DIR__).'/config/encryptable.php' => config_path('encryptable.php'),
            dirname(__DIR__).'/config/stubs/plugin-app.php' => config_path("plugin/{$vendor}/{$package}/app.php"),
            dirname(__DIR__).'/config/stubs/hyperf-plugin-autoload.php' => config_path("autoload/plugins/{$vendor}/{$package}.php"),
        ], 'encryptable-config');

        if (class_exists(\Illuminate\Validation\Rule::class)) {
            \Illuminate\Validation\Rule::macro(
                'uniqueEncrypted',
                fn (string $table, string $column = 'NULL') => new UniqueEncrypted($table, $column)
            );

            \Illuminate\Validation\Rule::macro(
                'existsEncrypted',
                fn (string $table, string $column = 'NULL') => new ExistsEncrypted($table, $column)
            );
        }
    }

    /**
     * Webman 官方：{@code config/plugin/{vendor}/{package}/app.php} 由框架合并为 {@code plugin.*.app.*}；
     * 仅在实际为 Webman 项目布局时使用该读取方式，避免 Laravel 安装同路径文件后被误判。
     */
    private function usesWebmanNativePluginConfig(): bool
    {
        if (! $this->isWebmanProjectLayout()) {
            return false;
        }

        $path = WebmanPluginEncryptableConfig::appConfigAbsolutePath();

        return $path !== null && is_file($path);
    }

    private function isWebmanProjectLayout(): bool
    {
        if (! function_exists('base_path')) {
            return false;
        }

        $root = base_path();

        return is_file($root.'/support/bootstrap.php')
            && (is_file($root.'/start.php') || is_file($root.'/windows.php'));
    }

    private function resolveIlluminateStyleEncryptableConfigPath(): string
    {
        [$vendor, $package] = PackagePluginPaths::splitVendorPackage();
        $plugin = config_path("plugin/{$vendor}/{$package}/app.php");
        if (is_file($plugin)) {
            return $plugin;
        }

        $legacy = config_path('encryptable.php');
        if (is_file($legacy)) {
            return $legacy;
        }

        return dirname(__DIR__).'/config/encryptable.php';
    }
}
