<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Webman;

class Install
{
    public static function install(): void
    {
    }

    public static function configPath(): string
    {
        return __DIR__ . '/config/clickhouse.php';
    }
}