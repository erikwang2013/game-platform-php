<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Migration\Migrator;
use Erikwang2013\ClickHouse\Migration\Repository;
use Illuminate\Console\Command;

class MigrationRunCommand extends Command
{
    protected $signature = 'clickhouse:migration:run';
    protected $description = 'Run pending ClickHouse migrations';

    public function handle(): int
    {
        $config = config('clickhouse.migrations');
        $repository = new Repository(
            ClickHouse::getManager()->connection(),
            $config['table'] ?? 'clickhouse_migrations',
        );
        $migrator = new Migrator(ClickHouse::getManager()->connection(), $repository, $config['path']);
        $run = $migrator->run();

        if (empty($run)) {
            $this->info('No pending migrations.');
        } else {
            foreach ($run as $migration) {
                $this->line("  <info>Migrated:</info> $migration");
            }
        }
        return 0;
    }
}