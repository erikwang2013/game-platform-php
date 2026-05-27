<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Schema\Builder;

class Migrator
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Repository $repository,
        private readonly string $path,
    ) {
    }

    public function install(): void
    {
        $this->repository->createRepository();
    }

    public function run(): array
    {
        $migrations = $this->loadMigrations();
        $ran = array_column($this->repository->getMigrations(), 'migration');
        $pending = array_diff($migrations, $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getLastBatch() + 1;
        $run = [];

        foreach ($pending as $file) {
            $migration = $this->resolve($file);
            $migration->up();
            $this->repository->log($file, $batch);
            $run[] = $file;
        }

        return $run;
    }

    public function rollback(?int $steps = null): array
    {
        $batch = $this->repository->getLastBatch();
        $migrations = $this->repository->getMigrationsByBatch($batch);

        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }

        $rolledBack = [];
        foreach ($migrations as $row) {
            $file = $row['migration'];
            $migration = $this->resolve($file);
            $migration->down();
            $this->repository->delete($file);
            $rolledBack[] = $file;
        }

        return $rolledBack;
    }

    public function refresh(): void
    {
        $this->rollback();
        $this->run();
    }

    private function loadMigrations(): array
    {
        $files = glob($this->path . '/*.php');
        return array_map(fn($f) => basename($f, '.php'), $files);
    }

    private function resolve(string $file): Migration
    {
        $path = $this->path . '/' . $file . '.php';
        require_once $path;

        $class = preg_replace('/^\d+_/', '', $file);
        $class = str_replace('_', '', ucwords($class, '_'));

        $instance = new $class();
        $instance->setSchema(new Builder($this->client));

        return $instance;
    }
}