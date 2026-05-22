<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Adapters\Laravel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Erikwang2013\Snowflake\Snowflake;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/snowflake.php',
            'snowflake'
        );

        $this->app->singleton(Snowflake::class, function ($app) {
            return Snowflake::fromConfig($app['config']['snowflake']);
        });

        $this->app->alias(Snowflake::class, 'snowflake');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/snowflake.php' => $this->app->configPath('snowflake.php'),
            ], 'snowflake-config');
        }
    }
}
