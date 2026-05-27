<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;

class NoPool implements PoolInterface
{
    private int $activeCount = 0;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
    }

    public function get(): ClientInterface
    {
        $maxActive = $this->config['max_connections'] ?? PHP_INT_MAX;

        if ($this->activeCount >= $maxActive) {
            throw new PoolException('NoPool: maximum connections exceeded');
        }

        $this->activeCount++;
        return ($this->factory)();
    }

    public function put(ClientInterface $client): void
    {
        $this->activeCount = max(0, $this->activeCount - 1);
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => 0,
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->activeCount = 0;
    }
}