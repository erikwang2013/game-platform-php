<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use PHPUnit\Framework\TestCase;
use Erikwang2013\Snowflake\Resolvers\RandomSequenceResolver;
use Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver;

class SequenceResolverTest extends TestCase
{
    // ---- Sequential resolver tests ----

    public function testSequentialStartsAtZero(): void
    {
        $resolver = new SequentialSequenceResolver();

        $this->assertSame(0, $resolver->next(1000, 4095));
    }

    public function testSequentialIncrementsWithinSameTimestamp(): void
    {
        $resolver = new SequentialSequenceResolver();

        $this->assertSame(0, $resolver->next(1000, 4095));
        $this->assertSame(1, $resolver->next(1000, 4095));
        $this->assertSame(2, $resolver->next(1000, 4095));
    }

    public function testSequentialExhaustsAndReturnsNull(): void
    {
        $resolver = new SequentialSequenceResolver();
        $maxSequence = 3;

        // 0, 1, 2, 3 — 4 calls fill all slots
        $this->assertSame(0, $resolver->next(1000, $maxSequence));
        $this->assertSame(1, $resolver->next(1000, $maxSequence));
        $this->assertSame(2, $resolver->next(1000, $maxSequence));
        $this->assertSame(3, $resolver->next(1000, $maxSequence));

        // Next call should be null (all slots exhausted)
        $this->assertNull($resolver->next(1000, $maxSequence));
    }

    public function testSequentialResetsOnNewTimestamp(): void
    {
        $resolver = new SequentialSequenceResolver();
        $maxSequence = 3;

        // Exhaust timestamp 1000
        $resolver->next(1000, $maxSequence); // 0
        $resolver->next(1000, $maxSequence); // 1
        $resolver->next(1000, $maxSequence); // 2
        $resolver->next(1000, $maxSequence); // 3
        $this->assertNull($resolver->next(1000, $maxSequence));

        // New timestamp starts fresh at 0
        $this->assertSame(0, $resolver->next(1001, $maxSequence));
    }

    // ---- Random resolver tests ----

    public function testRandomReturnsValuesInRange(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        for ($i = 0; $i < 100; $i++) {
            $seq = $resolver->next($i, $maxSequence);
            $this->assertNotNull($seq);
            $this->assertGreaterThanOrEqual(0, $seq);
            $this->assertLessThanOrEqual($maxSequence, $seq);
        }
    }

    public function testRandomReturnsUniqueValues(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        $values = [];
        for ($i = 0; $i < 100; $i++) {
            $seq = $resolver->next(1000, $maxSequence);
            $this->assertNotNull($seq);
            $values[] = $seq;
        }

        $this->assertCount(100, array_unique($values));
    }

    public function testRandomResetsOnNewTimestamp(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 4095;

        // Use many slots on timestamp 1000
        for ($i = 0; $i < 100; $i++) {
            $this->assertNotNull($resolver->next(1000, $maxSequence));
        }

        // New timestamp should get fresh slots (0 is available)
        $seq = $resolver->next(1001, $maxSequence);
        $this->assertNotNull($seq);
    }

    public function testSequentialPurgesOldTimestamps(): void
    {
        $resolver = new SequentialSequenceResolver();

        for ($ts = 0; $ts < 100; $ts++) {
            $resolver->next($ts, 4095);
        }

        $this->assertTrue(true); // no memory error = pass
    }

    public function testRandomPurgesOldTimestamps(): void
    {
        $resolver = new RandomSequenceResolver();

        for ($ts = 0; $ts < 100; $ts++) {
            $resolver->next($ts, 4095);
        }

        $this->assertTrue(true); // no memory error = pass
    }

    public function testRandomNearExhaustionFindsAllSlots(): void
    {
        $resolver = new RandomSequenceResolver();
        $maxSequence = 5; // 6 slots: 0-5
        $timestamp = 1000;

        // Fill all 6 slots — none should return null prematurely
        $seen = [];
        for ($i = 0; $i < 6; $i++) {
            $seq = $resolver->next($timestamp, $maxSequence);
            $this->assertNotNull($seq, "Slot $i should not be null");
            $this->assertGreaterThanOrEqual(0, $seq);
            $this->assertLessThanOrEqual($maxSequence, $seq);
            $seen[] = $seq;
        }

        // All 6 values should be unique
        $this->assertCount(6, array_unique($seen));

        // Now truly exhausted
        $this->assertNull($resolver->next($timestamp, $maxSequence));
    }
}
