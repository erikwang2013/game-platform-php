<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\Transport;

use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpTransportTest extends TestCase
{
    public function testBindParamsReplacesPlaceholders(): void
    {
        $config = new Config([
            'host' => 'localhost',
            'port' => 8123,
            'username' => 'default',
            'password' => '',
            'database' => 'default',
        ]);

        $transport = new HttpTransport($config);

        $ref = new \ReflectionMethod($transport, 'bindParams');
        $result = $ref->invoke($transport, 'SELECT * FROM t WHERE id = ? AND name = ?', [1, 'test']);

        $this->assertSame("SELECT * FROM t WHERE id = 1 AND name = 'test'", $result);
    }

    public function testBindParamsQuotesNull(): void
    {
        $config = new Config([
            'host' => 'localhost', 'port' => 8123,
            'username' => 'default', 'password' => '', 'database' => 'default',
        ]);
        $transport = new HttpTransport($config);

        $ref = new \ReflectionMethod($transport, 'bindParams');
        $result = $ref->invoke($transport, 'SELECT * FROM t WHERE col = ?', [null]);

        $this->assertSame('SELECT * FROM t WHERE col = NULL', $result);
    }
}