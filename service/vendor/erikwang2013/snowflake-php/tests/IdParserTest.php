<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Snowflake;

class IdParserTest extends TestCase
{
    public function testStaticParseOnGeneratedId(): void
    {
        $snowflake = new Snowflake(workerId: 8, datacenterId: 12);
        $id = $snowflake->id();
        $parsed = Snowflake::parse($id);

        $this->assertSame(8, $parsed['worker_id']);
        $this->assertSame(12, $parsed['datacenter_id']);
        $this->assertArrayHasKey('datetime', $parsed);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/',
            $parsed['datetime']
        );
    }

    public function testParseWithCustomEpoch(): void
    {
        $epoch = 1577836800000; // 2020-01-01
        $snowflake = new Snowflake(workerId: 1, datacenterId: 1, epoch: $epoch);
        $id = $snowflake->id();

        // Parse with matching epoch
        $parsed = Snowflake::parse($id, $epoch);
        $this->assertSame(1, $parsed['worker_id']);
        $this->assertSame(1, $parsed['datacenter_id']);

        // Parse with different epoch should give wrong timestamp
        $wrongParsed = Snowflake::parse($id, 0);
        $this->assertNotEquals($parsed['timestamp_ms'], $wrongParsed['timestamp_ms']);
    }

    public function testParseExtractsCorrectComponents(): void
    {
        $snowflake = new Snowflake(workerId: 31, datacenterId: 31);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        // Default: 5 bits each, max = 31
        $this->assertLessThanOrEqual(31, $parsed['worker_id']);
        $this->assertLessThanOrEqual(31, $parsed['datacenter_id']);
        $this->assertLessThanOrEqual(4095, $parsed['sequence']); // 12 bits = 0-4095
    }

    public function testParseIdVsStaticParseConsistency(): void
    {
        $snowflake = new Snowflake(); // default config
        $id = $snowflake->id();

        $instanceParsed = $snowflake->parseId($id);
        $staticParsed = Snowflake::parse($id);

        $this->assertSame($instanceParsed['worker_id'], $staticParsed['worker_id']);
        $this->assertSame($instanceParsed['datacenter_id'], $staticParsed['datacenter_id']);
        $this->assertSame($instanceParsed['sequence'], $staticParsed['sequence']);
        // Timestamps should match since both use default epoch
        $this->assertSame($instanceParsed['timestamp_ms'], $staticParsed['timestamp_ms']);
    }

    public function testParseSpecialValues(): void
    {
        // Generate with worker=0, dc=0
        $snowflake = new Snowflake(workerId: 0, datacenterId: 0);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
    }
}
