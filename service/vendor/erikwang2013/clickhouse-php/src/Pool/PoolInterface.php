<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;

interface PoolInterface
{
    public function get(): ClientInterface;
    public function put(ClientInterface $client): void;
    public function stats(): array;
    public function close(): void;
}