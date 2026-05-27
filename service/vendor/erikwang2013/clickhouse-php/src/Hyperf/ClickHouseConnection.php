<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Query\Result;
use Erikwang2013\ClickHouse\Hyperf\Pool\PoolFactory;

class ClickHouseConnection
{
    private ?ClientInterface $client = null;

    public function __construct(
        private readonly PoolFactory $poolFactory,
    ) {
    }

    public function connection(string $name = 'default'): ClientInterface
    {
        if ($this->client === null) {
            $pool = $this->poolFactory->getPool($name);
            $this->client = $pool->get();
        }
        return $this->client;
    }

    public function table(string $table): Builder
    {
        return (new Builder($this->connection()))->table($table);
    }

    public function query(string $sql, array $bindings = []): Result
    {
        return $this->connection()->query($sql, $bindings);
    }

    public function release(): void
    {
        if ($this->client !== null) {
            $pool = $this->poolFactory->getPool('default');
            $pool->put($this->client);
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}