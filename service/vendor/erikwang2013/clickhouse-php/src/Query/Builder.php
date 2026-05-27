<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Query;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Builder
{
    public array $columns = [];
    public string $from = '';
    public array $wheres = [];
    public array $orders = [];
    public array $groups = [];
    public ?int $limit = null;
    public ?int $offset = null;
    public array $bindings = [];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly Grammar $grammar = new Grammar(),
    ) {
    }

    public function table(string $table): static
    {
        $this->from = $table;
        return $this;
    }

    public function from(string $table): static
    {
        return $this->table($table);
    }

    public function select(string|array $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function selectRaw(string $expression): static
    {
        $this->columns[] = $expression;
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        $this->wheres[] = ['basic', $column, $operator, $value, $boolean];
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values): static
    {
        $this->wheres[] = ['in', $column, 'in', $values, 'and'];
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->wheres[] = ['in', $column, 'not in', $values, 'and'];
        return $this;
    }

    public function whereBetween(string $column, array $values): static
    {
        $this->wheres[] = ['between', $column, 'between', $values, 'and'];
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['null', $column, 'null', null, 'and'];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['null', $column, 'not null', null, 'and'];
        return $this;
    }

    public function whereRaw(string $sql, string $boolean = 'and'): static
    {
        $this->wheres[] = ['raw', $sql, null, null, $boolean];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [$column, strtoupper($direction)];
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): Result
    {
        $sql = $this->grammar->compileSelect($this);
        return $this->client->query($sql, $this->bindings);
    }

    public function first(): mixed
    {
        return $this->limit(1)->get()->first();
    }

    public function count(): int
    {
        $this->columns = ['count(*) as aggregate'];
        $row = $this->get()->first();
        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $this->columns = ["sum($column) as aggregate"];
        $row = $this->get()->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    public function avg(string $column): float
    {
        $this->columns = ["avg($column) as aggregate"];
        $row = $this->get()->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    public function min(string $column): mixed
    {
        $this->columns = ["min($column) as aggregate"];
        $row = $this->get()->first();
        return $row['aggregate'] ?? null;
    }

    public function max(string $column): mixed
    {
        $this->columns = ["max($column) as aggregate"];
        $row = $this->get()->first();
        return $row['aggregate'] ?? null;
    }

    public function insert(array $data): int
    {
        $sql = $this->grammar->compileInsert($this, $data);
        $this->client->query($sql);
        return isset($data[0]) && is_array($data[0]) ? count($data) : 1;
    }

    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this);
        $result = $this->client->query($sql);
        return $result->count();
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }
}