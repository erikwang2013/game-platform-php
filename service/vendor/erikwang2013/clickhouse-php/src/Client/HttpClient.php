<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Query\Result;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\TransportInterface;

class HttpClient implements ClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly Config $config,
    ) {
    }

    public function query(string $sql, array $bindings = []): Result
    {
        $result = $this->transport->send($sql, $bindings);

        if (is_array($result)) {
            if (isset($result['rows'])) {
                return new Result($result['rows'], $result['rows_before_limit_at_least'] ?? 0, $result['meta'] ?? null);
            }
            return new Result($result);
        }

        return new Result([]);
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->toArray();
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $columns = array_keys($data[0] ?? $data);
        $values = [];

        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $escaped = array_map(fn($v) => $this->escape($v), array_values($row));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
        } else {
            $escaped = array_map(fn($v) => $this->escape($v), array_values($data));
            $values[] = '(' . implode(', ', $escaped) . ')';
        }

        $tableQuoted = implode('.', array_map(fn($p) => "`$p`", explode('.', $table)));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $tableQuoted,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $values),
        );

        $this->query($sql);
        return count($values);
    }

    public function ping(): bool
    {
        try {
            $this->transport->send('SELECT 1', []);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function escape(mixed $value): string
    {
        if (is_null($value)) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}