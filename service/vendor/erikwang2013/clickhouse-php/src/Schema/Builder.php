<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Schema;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Builder
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Grammar $grammar = new Grammar(),
    ) {
    }

    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);

        if (empty($blueprint->columns)) {
            return;
        }

        $sql = $this->grammar->compileCreate($table, $blueprint);
        $this->client->query($sql);
    }

    public function drop(string $table): void
    {
        $this->client->query($this->grammar->compileDrop($table));
    }

    public function alter(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        $this->client->query($this->grammar->compileAlter($table, $blueprint));
    }

    public function hasTable(string $table): bool
    {
        try {
            $this->client->query($this->grammar->compileTableExists($table));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getTables(string $database = 'default'): array
    {
        return $this->client->select($this->grammar->compileTableList($database));
    }

    public function getTableInfo(string $table): array
    {
        return $this->client->select($this->grammar->compileTableInfo($table));
    }
}