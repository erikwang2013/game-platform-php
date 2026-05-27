<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf\Command;

use Erikwang2013\ClickHouse\Hyperf\ClickHouseConnection;
use Hyperf\Command\Command;

class ClickHouseCommand extends Command
{
    protected ?string $name = 'clickhouse:table-list';
    protected string $description = 'List all tables in ClickHouse';

    public function __construct(
        private readonly ClickHouseConnection $clickhouse,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $tables = $this->clickhouse->connection()->select('SHOW TABLES');
        $this->info('ClickHouse Tables:');
        foreach ($tables as $table) {
            $this->line('  ' . ($table['name'] ?? reset($table)));
        }
    }
}