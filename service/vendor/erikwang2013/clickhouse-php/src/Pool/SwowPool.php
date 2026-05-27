<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;
use Swow\Channel;

class SwowPool implements PoolInterface
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
        $this->minConnections = $config['min_connections'] ?? 2;
        $this->maxConnections = $config['max_connections'] ?? 16;
        $this->connectionTimeout = $config['connection_timeout'] ?? 5.0;

        $this->channel = new Channel($this->maxConnections);

        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->channel->push(($this->factory)(), (int) ($this->connectionTimeout * 1000));
            $this->activeCount++;
        }
    }

    public function get(): ClientInterface
    {
        $client = $this->channel->pop((int) ($this->connectionTimeout * 1000));

        if ($client === false) {
            if ($this->activeCount < $this->maxConnections) {
                $client = ($this->factory)();
                $this->activeCount++;
            } else {
                throw new PoolException('SwowPool: connection pool exhausted');
            }
        }

        return $client;
    }

    public function put(ClientInterface $client): void
    {
        $this->channel->push($client, (int) ($this->connectionTimeout * 1000));
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => $this->channel->getLength(),
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->channel->close();
    }
}