<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Pool\NoPool;
use PHPUnit\Framework\TestCase;
use Mockery;

class PoolTest extends TestCase
{
    public function testNoPoolGetsClient(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client);

        $this->assertSame($client, $pool->get());
        $this->assertSame(['active' => 1, 'idle' => 0, 'total' => 1], $pool->stats());
    }

    public function testNoPoolPutDecrementsCount(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client);

        $c = $pool->get();
        $this->assertSame(1, $pool->stats()['active']);
        $pool->put($c);
        $this->assertSame(0, $pool->stats()['active']);
    }

    public function testNoPoolMaxConnections(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client, ['max_connections' => 2]);
        $pool->get();
        $pool->get();

        $this->expectException(\Erikwang2013\ClickHouse\Exceptions\PoolException::class);
        $pool->get();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}