<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse;

use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Schema\Builder as SchemaBuilder;

class ClickHouse
{
    protected static ?Manager $manager = null;

    public static function setManager(Manager $manager): void
    {
        static::$manager = $manager;
    }

    public static function getManager(): Manager
    {
        return static::$manager;
    }

    public static function connection(?string $name = null): Builder
    {
        $client = static::$manager->connection($name);
        return new Builder($client);
    }

    public static function table(string $table, ?string $connection = null): Builder
    {
        return static::connection($connection)->table($table);
    }

    public static function schema(): SchemaBuilder
    {
        return new SchemaBuilder(static::$manager->connection());
    }

    public static function query(string $sql, array $bindings = []): Query\Result
    {
        return static::$manager->connection()->query($sql, $bindings);
    }
}