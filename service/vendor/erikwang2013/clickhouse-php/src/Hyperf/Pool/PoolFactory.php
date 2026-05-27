<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf\Pool;

use Erikwang2013\ClickHouse\Pool\PoolInterface;
use Erikwang2013\ClickHouse\Support\Config as ClickHouseConfig;
use Erikwang2013\ClickHouse\Transport\HttpTransport;

class PoolFactory
{
    private array $pools = [];

    public function __construct(
        private readonly array $config,
    ) {
    }

    public function getPool(string $name = 'default'): PoolInterface
    {
        if (!isset($this->pools[$name])) {
            $connectionConfig = $this->config['connections'][$name]
                ?? throw new \RuntimeException("ClickHouse connection [{$name}] not configured.");

            $poolConfig = $this->config['pool'] ?? [];

            $this->pools[$name] = new ClickHousePool(
                function () use ($connectionConfig) {
                    $cfg = new ClickHouseConfig($connectionConfig);
                    return new \Erikwang2013\ClickHouse\Client\HttpClient(
                        new HttpTransport($cfg),
                        $cfg,
                    );
                },
                $poolConfig,
            );
        }
        return $this->pools[$name];
    }
}