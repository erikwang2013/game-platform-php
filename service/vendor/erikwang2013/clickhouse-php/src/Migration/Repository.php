<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Repository
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $table = 'migrations',
    ) {
    }

    public function createRepository(): void
    {
        $this->client->query("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                migration String,
                batch UInt32,
                executed_at DateTime DEFAULT now()
            ) ENGINE = MergeTree()
            ORDER BY migration
        ");
    }

    public function getMigrations(): array
    {
        return $this->client->select("SELECT migration FROM {$this->table} ORDER BY migration");
    }

    public function getLastBatch(): int
    {
        $result = $this->client->select("SELECT max(batch) as batch FROM {$this->table}");
        return (int) ($result[0]['batch'] ?? 0);
    }

    public function log(string $migration, int $batch): void
    {
        $this->client->insert($this->table, ['migration' => $migration, 'batch' => $batch]);
    }

    public function delete(string $migration): void
    {
        $this->client->query("ALTER TABLE {$this->table} DELETE WHERE migration = ?", [$migration]);
    }

    public function getMigrationsByBatch(int $batch): array
    {
        return $this->client->select(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY migration DESC",
            [$batch],
        );
    }
}