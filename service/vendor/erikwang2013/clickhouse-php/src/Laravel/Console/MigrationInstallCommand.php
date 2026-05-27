<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Migration\Repository;
use Illuminate\Console\Command;

class MigrationInstallCommand extends Command
{
    protected $signature = 'clickhouse:migration:install';
    protected $description = 'Create the ClickHouse migration repository';

    public function handle(): int
    {
        $config = config('clickhouse.migrations');
        $repository = new Repository(
            ClickHouse::getManager()->connection(),
            $config['table'] ?? 'clickhouse_migrations',
        );
        $repository->createRepository();
        $this->info('ClickHouse migration table created successfully.');
        return 0;
    }
}