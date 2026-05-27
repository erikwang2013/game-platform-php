<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Query\Result;

interface ClientInterface
{
    public function query(string $sql, array $bindings = []): Result;
    public function select(string $sql, array $bindings = []): array;
    public function insert(string $table, array $data): int;
    public function ping(): bool;
}