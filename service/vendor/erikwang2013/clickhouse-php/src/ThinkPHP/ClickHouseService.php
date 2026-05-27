<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\ThinkPHP;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use think\Service;

class ClickHouseService extends Service
{
    public function register(): void
    {
        $this->app->bind('clickhouse', function () {
            $config = $this->app->config->get('clickhouse', []);
            $manager = new Manager($config);
            ClickHouse::setManager($manager);
            return $manager;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                command\ClickHouse::class,
            ]);
        }
    }
}