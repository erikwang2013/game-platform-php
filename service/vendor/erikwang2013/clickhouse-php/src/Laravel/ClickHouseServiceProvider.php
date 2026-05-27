<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Laravel;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use Illuminate\Support\ServiceProvider;

class ClickHouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/clickhouse.php', 'clickhouse',
        );

        $this->app->singleton('clickhouse', function ($app) {
            $config = $app['config']['clickhouse'];
            $logger = $config['query_log'] ? $app['log'] : null;
            $manager = new Manager($config, $logger);
            ClickHouse::setManager($manager);
            return $manager;
        });

        $this->app->alias('clickhouse', Manager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/clickhouse.php' => config_path('clickhouse.php'),
        ], 'clickhouse-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\TableListCommand::class,
                Console\MigrationInstallCommand::class,
                Console\MigrationRunCommand::class,
            ]);
        }
    }
}