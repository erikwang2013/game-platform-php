<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class ClickHouse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'clickhouse';
    }
}