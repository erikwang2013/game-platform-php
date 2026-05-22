<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Exceptions\ClockDriftException;
use Erikwang2013\Snowflake\Exceptions\InvalidDatacenterIdException;
use Erikwang2013\Snowflake\Exceptions\InvalidWorkerIdException;
use Erikwang2013\Snowflake\Snowflake;

class SnowflakeTest extends TestCase
{
    public function testGeneratedIdIsPositive(): void
    {
        $snowflake = new Snowflake();
        $id = $snowflake->id();

        $this->assertGreaterThan(0, $id);
        $this->assertLessThanOrEqual(PHP_INT_MAX, $id);
    }

    public function testGeneratedIdIsInteger(): void
    {
        $snowflake = new Snowflake();
        $this->assertIsInt($snowflake->id());
    }

    public function testMultipleIdsAreUnique(): void
    {
        $snowflake = new Snowflake();
        $ids = [];

        for ($i = 0; $i < 10000; $i++) {
            $ids[] = $snowflake->id();
        }

        $this->assertCount(10000, array_unique($ids));
    }

    public function testIdsAreMonotonicallyIncreasing(): void
    {
        $snowflake = new Snowflake();
        $prev = $snowflake->id();

        for ($i = 0; $i < 5000; $i++) {
            $next = $snowflake->id();
            $this->assertGreaterThan($prev, $next);
            $prev = $next;
        }
    }

    public function testNextIdIsAliasForId(): void
    {
        $snowflake = new Snowflake();
        $id1 = $snowflake->id();
        $id2 = $snowflake->nextId();

        $this->assertIsInt($id1);
        $this->assertIsInt($id2);
        $this->assertGreaterThan($id1, $id2);
    }

    public function testWorkerIdReflectedInParsedId(): void
    {
        $snowflake = new Snowflake(workerId: 15, datacenterId: 0);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(15, $parsed['worker_id']);
    }

    public function testDatacenterIdReflectedInParsedId(): void
    {
        $snowflake = new Snowflake(workerId: 0, datacenterId: 20);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(20, $parsed['datacenter_id']);
    }

    public function testParseRoundTrip(): void
    {
        $snowflake = new Snowflake(workerId: 10, datacenterId: 7);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(10, $parsed['worker_id']);
        $this->assertSame(7, $parsed['datacenter_id']);
        $this->assertArrayHasKey('timestamp_ms', $parsed);
        $this->assertArrayHasKey('datetime', $parsed);
        $this->assertArrayHasKey('sequence', $parsed);
        $this->assertIsInt($parsed['sequence']);
        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
    }

    public function testStaticParse(): void
    {
        $snowflake = new Snowflake(workerId: 5, datacenterId: 3);
        $id = $snowflake->id();
        $parsed = Snowflake::parse($id);

        $this->assertSame(5, $parsed['worker_id']);
        $this->assertSame(3, $parsed['datacenter_id']);
    }

    public function testDefaultWorkerAndDatacenterAreZero(): void
    {
        $snowflake = new Snowflake();
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(0, $parsed['worker_id']);
        $this->assertSame(0, $parsed['datacenter_id']);
    }

    public function testInvalidWorkerIdThrows(): void
    {
        $this->expectException(InvalidWorkerIdException::class);
        new Snowflake(workerId: 32); // max is 31 with default 5 bits
    }

    public function testInvalidDatacenterIdThrows(): void
    {
        $this->expectException(InvalidDatacenterIdException::class);
        new Snowflake(datacenterId: 32); // max is 31 with default 5 bits
    }

    public function testNegativeWorkerIdThrows(): void
    {
        $this->expectException(InvalidWorkerIdException::class);
        new Snowflake(workerId: -1);
    }

    public function testNegativeDatacenterIdThrows(): void
    {
        $this->expectException(InvalidDatacenterIdException::class);
        new Snowflake(datacenterId: -1);
    }

    public function testCustomBitLayout(): void
    {
        $snowflake = new Snowflake(
            workerId: 500,
            datacenterId: 8,
            workerBits: 10,
            datacenterBits: 4,
            sequenceBits: 8,
        );

        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(500, $parsed['worker_id']);
        $this->assertSame(8, $parsed['datacenter_id']);
    }

    public function testMaxWorkerIdWithCustomBits(): void
    {
        // 10 bits = 1024 max (0-1023)
        $snowflake = new Snowflake(
            workerId: 1023,
            workerBits: 10,
            datacenterBits: 4,
            sequenceBits: 8,
        );
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(1023, $parsed['worker_id']);
    }

    public function testFromConfigFactory(): void
    {
        $snowflake = Snowflake::fromConfig([
            'worker_id' => 3,
            'datacenter_id' => 7,
            'epoch' => 1704067200000,
        ]);

        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertSame(3, $parsed['worker_id']);
        $this->assertSame(7, $parsed['datacenter_id']);
    }

    public function testInvalidBitAllocationThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Snowflake(
            workerBits: 32,
            datacenterBits: 32,
        ); // total = 64 > 63
    }

    public function testMinimumBitCountEnforced(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Snowflake(sequenceBits: 0);
    }

    public function testCustomEpoch(): void
    {
        // Use a known epoch in the past
        $epoch = 1577836800000; // 2020-01-01 00:00:00 UTC
        $snowflake = new Snowflake(epoch: $epoch);
        $id = $snowflake->id();
        $parsed = $snowflake->parseId($id);

        $this->assertGreaterThanOrEqual($epoch, $parsed['timestamp_ms']);
    }

    public function testFromConfigThrowsOnNonexistentResolverClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and implement');

        Snowflake::fromConfig([
            'sequence_resolver' => 'Some\\Nonexistent\\Class',
        ]);
    }

    public function testFromConfigThrowsOnResolverNotImplementingInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must exist and implement');

        Snowflake::fromConfig([
            'sequence_resolver' => \stdClass::class,
        ]);
    }

    public function testClockDriftExceptionIsThrown(): void
    {
        $snowflake = new Snowflake(clockToleranceMs: 0);

        // Use reflection to force lastTimestamp into the future,
        // simulating a backward clock jump.
        $ref = new \ReflectionProperty(Snowflake::class, 'lastTimestamp');
        $ref->setAccessible(true);
        $ref->setValue($snowflake, PHP_INT_MAX);

        $this->expectException(ClockDriftException::class);
        $snowflake->id();
    }

    public function testClockDriftWithinToleranceDoesNotThrow(): void
    {
        $snowflake = new Snowflake(clockToleranceMs: 60_000);

        $now = (int) (microtime(true) * 1000);
        $ref = new \ReflectionProperty(Snowflake::class, 'lastTimestamp');
        $ref->setAccessible(true);
        $ref->setValue($snowflake, $now + 1000); // 1 second ahead

        // Should not throw — 1s drift is within the 60s tolerance.
        $id = $snowflake->id();
        $this->assertGreaterThan(0, $id);
    }

    public function testMultiInstanceIdsAreUnique(): void
    {
        $a = new Snowflake(workerId: 0, datacenterId: 0);
        $b = new Snowflake(workerId: 1, datacenterId: 0);

        $ids = [];
        for ($i = 0; $i < 5000; $i++) {
            $ids[] = $a->id();
            $ids[] = $b->id();
        }

        $this->assertCount(10000, array_unique($ids));
    }
}
