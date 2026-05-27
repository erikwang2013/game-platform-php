<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Webman;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;

class ClickHouseService
{
    private static ?Manager $manager = null;

    public static function instance(): Manager
    {
        if (self::$manager === null) {
            $config = config('plugin.erikwang2013.clickhouse-php.app', []);
            $manager = new Manager($config);
            ClickHouse::setManager($manager);
            self::$manager = $manager;
        }
        return self::$manager;
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return ClickHouse::$method(...$arguments);
    }
}