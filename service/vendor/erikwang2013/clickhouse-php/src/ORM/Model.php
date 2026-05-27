<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\ORM;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Query\Builder;

abstract class Model
{
    protected string $table = '';
    protected string $connection = 'default';
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function newQuery(): Builder
    {
        $manager = ClickHouse::getManager();
        $client = $manager->connection($this->connection);
        return (new Builder($client))->table($this->getTable());
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function save(): void
    {
        $this->newQuery()->insert($this->attributes);
    }

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    public static function find(int|string $id): ?static
    {
        return static::where('id', $id)->first();
    }

    public static function all(): Collection
    {
        $rows = static::query()->get()->toArray();
        return new Collection(array_map(fn($row) => new static($row), $rows));
    }

    public static function insert(array $data): int
    {
        return static::query()->insert($data);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): Builder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }
}