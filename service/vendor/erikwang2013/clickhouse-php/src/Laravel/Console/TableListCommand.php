<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Illuminate\Console\Command;

class TableListCommand extends Command
{
    protected $signature = 'clickhouse:table-list {database? : Database name}';
    protected $description = 'List all tables in ClickHouse';

    public function handle(): int
    {
        $database = $this->argument('database') ?? 'default';
        $tables = ClickHouse::schema()->getTables($database);
        $this->table(['Table'], array_map(fn($t) => [$t['name'] ?? $t], $tables));
        return 0;
    }
}