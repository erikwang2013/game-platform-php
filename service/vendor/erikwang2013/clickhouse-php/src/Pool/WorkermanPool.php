<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;
use Workerman\Coroutine\Channel;

class WorkermanPool implements PoolInterface
{
    private Channel $channel;
    private int $activeCount = 0;
    private int $minConnections;
    private int $maxConnections;
    private float $connectionTimeout;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
        $this->minConnections = $config['min_connections'] ?? 1;
        $this->maxConnections = $config['max_connections'] ?? 8;
        $this->connectionTimeout = $config['connection_timeout'] ?? 5.0;

        $this->channel = new Channel($this->maxConnections);

        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->channel->push(($this->factory)(), $this->connectionTimeout);
            $this->activeCount++;
        }
    }

    public function get(): ClientInterface
    {
        $client = $this->channel->pop($this->connectionTimeout);

        if ($client === false) {
            if ($this->activeCount < $this->maxConnections) {
                $client = ($this->factory)();
                $this->activeCount++;
            } else {
                throw new PoolException('WorkermanPool: connection pool exhausted');
            }
        }

        return $client;
    }

    public function put(ClientInterface $client): void
    {
        $this->channel->push($client, $this->connectionTimeout);
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => $this->channel->isEmpty() ? 0 : $this->activeCount,
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->channel->close();
    }
}