<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Query;

class Grammar
{
    public function compileSelect(Builder $builder): string
    {
        if (empty($builder->columns)) {
            $builder->select('*');
        }

        $sql = 'SELECT ' . implode(', ', $builder->columns);
        $sql .= ' FROM ' . $builder->from;

        return $this->compileWheres($builder, $sql)
            . $this->compileGroups($builder)
            . $this->compileOrders($builder)
            . $this->compileLimit($builder);
    }

    public function compileInsert(Builder $builder, array $data): string
    {
        $columns = array_keys($data[0] ?? $data);
        $values = [];

        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $escaped = array_map(fn($v) => $this->quote($v), array_values($row));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
        } else {
            $escaped = array_map(fn($v) => $this->quote($v), array_values($data));
            $values[] = '(' . implode(', ', $escaped) . ')';
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $builder->from,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $values),
        );
    }

    public function compileDelete(Builder $builder): string
    {
        $sql = 'ALTER TABLE ' . $builder->from . ' DELETE';
        return $this->compileWheres($builder, $sql);
    }

    private function compileWheres(Builder $builder, string $sql): string
    {
        if (empty($builder->wheres)) {
            return $sql;
        }

        $clauses = [];
        foreach ($builder->wheres as $where) {
            [$type, $column, $operator, $value, $boolean] = $where;

            $prefix = empty($clauses) ? '' : ($boolean === 'or' ? 'OR ' : 'AND ');

            if ($type === 'raw') {
                $clauses[] = $prefix . $column;
                continue;
            }

            if ($type === 'basic') {
                $clauses[] = $prefix . $column . ' ' . $operator . ' ' . $this->quote($value);
            } elseif ($type === 'in') {
                $values = implode(', ', array_map(fn($v) => $this->quote($v), (array) $value));
                $not = $operator === 'not in' ? 'NOT ' : '';
                $clauses[] = $prefix . $column . ' ' . $not . 'IN (' . $values . ')';
            } elseif ($type === 'between') {
                $clauses[] = $prefix . $column . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]);
            } elseif ($type === 'null') {
                $not = $operator === 'not null' ? 'NOT ' : '';
                $clauses[] = $prefix . $column . ' IS ' . $not . 'NULL';
            }
        }

        return $sql . ' WHERE ' . implode(' ', $clauses);
    }

    private function compileGroups(Builder $builder): string
    {
        if (empty($builder->groups)) {
            return '';
        }
        return ' GROUP BY ' . implode(', ', $builder->groups);
    }

    private function compileOrders(Builder $builder): string
    {
        if (empty($builder->orders)) {
            return '';
        }
        $orders = [];
        foreach ($builder->orders as [$column, $direction]) {
            $orders[] = $column . ' ' . $direction;
        }
        return ' ORDER BY ' . implode(', ', $orders);
    }

    private function compileLimit(Builder $builder): string
    {
        $sql = '';
        if ($builder->limit !== null) {
            $sql .= ' LIMIT ' . $builder->limit;
        }
        if ($builder->offset !== null) {
            $sql .= ' OFFSET ' . $builder->offset;
        }
        return $sql;
    }

    public function quote(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }
        if (is_null($value)) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}