<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\Client;

use Erikwang2013\ClickHouse\Client\HttpClient;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class HttpClientTest extends TestCase
{
    public function testSelectReturnsArray(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')
            ->with('SELECT * FROM test', [])
            ->andReturn(['rows' => [['id' => 1]]]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $result = $client->select('SELECT * FROM test');
        $this->assertSame([['id' => 1]], $result);
    }

    public function testInsertGeneratesSql(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andReturn(['rows' => []]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $count = $client->insert('test', ['name' => 'foo', 'value' => 42]);
        $this->assertSame(1, $count);
    }

    public function testInsertBatchReturnsCount(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andReturn(['rows' => []]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $rows = [
            ['name' => 'a', 'value' => 1],
            ['name' => 'b', 'value' => 2],
        ];
        $count = $client->insert('test', $rows);
        $this->assertSame(2, $count);
    }

    public function testPingReturnsTrueOnSuccess(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->with('SELECT 1', [])->andReturn(['rows' => [[1]]]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $this->assertTrue($client->ping());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}