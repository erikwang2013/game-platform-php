<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\ThinkPHP;

use think\Facade;

class Facade extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'clickhouse';
    }
}