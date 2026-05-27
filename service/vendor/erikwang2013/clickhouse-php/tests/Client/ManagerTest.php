<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\Client;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{
    private array $config = [
        'default' => 'clickhouse',
        'connections' => [
            'clickhouse' => [
                'driver' => 'http',
                'host' => 'localhost',
                'port' => 8123,
                'username' => 'default',
                'password' => '',
                'database' => 'default',
            ],
            'native' => [
                'driver' => 'tcp',
                'host' => 'localhost',
                'port' => 9000,
                'username' => 'default',
                'password' => '',
                'database' => 'default',
            ],
        ],
    ];

    public function testConnectionReturnsClient(): void
    {
        $manager = new Manager($this->config);
        $client = $manager->connection();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testConnectionReturnsSameInstance(): void
    {
        $manager = new Manager($this->config);
        $a = $manager->connection('clickhouse');
        $b = $manager->connection('clickhouse');
        $this->assertSame($a, $b);
    }

    public function testUnknownConnectionThrows(): void
    {
        $manager = new Manager($this->config);
        $this->expectException(ConnectionException::class);
        $manager->connection('nonexistent');
    }

    public function testDefaultConnection(): void
    {
        $manager = new Manager($this->config);
        $client = $manager->connection();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }
}