<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Contracts;

interface SequenceResolver
{
    /**
     * Return a sequence number for the given timestamp, or null if all slots
     * in the current millisecond have been exhausted.
     *
     * @param int $timestamp   Timestamp offset (current_ms - epoch).
     * @param int $maxSequence Maximum sequence value (2^sequence_bits - 1).
     * @return int|null A value between 0 and $maxSequence, or null if exhausted.
     */
    public function next(int $timestamp, int $maxSequence): ?int;
}
