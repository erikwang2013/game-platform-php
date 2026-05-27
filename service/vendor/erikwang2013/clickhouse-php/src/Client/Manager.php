<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use Erikwang2013\ClickHouse\Pool\PoolInterface;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\HttpTransport;
use Erikwang2013\ClickHouse\Transport\TcpTransport;
use Erikwang2013\ClickHouse\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Manager
{
    private array $connections = [];
    private array $pools = [];
    private string $defaultConnection;

    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->defaultConnection = $config['default'] ?? 'default';
    }

    public function connection(?string $name = null): ClientInterface
    {
        $name ??= $this->defaultConnection;

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (isset($this->pools[$name])) {
            return $this->pools[$name]->get();
        }

        return $this->connections[$name] = $this->make($name);
    }

    public function setPool(string $name, PoolInterface $pool): void
    {
        $this->pools[$name] = $pool;
    }

    private function make(string $name): ClientInterface
    {
        $connections = $this->config['connections'] ?? [];

        if (!isset($connections[$name])) {
            throw new ConnectionException("ClickHouse connection [{$name}] not configured.");
        }

        $connConfig = new Config($connections[$name]);
        $transport = $this->createTransport($connConfig);

        return new HttpClient($transport, $connConfig);
    }

    private function createTransport(Config $config): TransportInterface
    {
        $driver = $config->get('driver', 'http');

        return match ($driver) {
            'native', 'tcp' => new TcpTransport($config),
            default => new HttpTransport($config),
        };
    }
}