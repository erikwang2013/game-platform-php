<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\ThinkPHP\command;

use Erikwang2013\ClickHouse\ClickHouse;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ClickHouse extends Command
{
    protected function configure(): void
    {
        $this->setName('clickhouse:table-list')
            ->setDescription('List all tables in ClickHouse');
    }

    protected function execute(Input $input, Output $output): void
    {
        $tables = ClickHouse::schema()->getTables();
        $output->writeln('<info>ClickHouse Tables:</info>');
        foreach ($tables as $table) {
            $output->writeln('  ' . ($table['name'] ?? $table));
        }
    }
}