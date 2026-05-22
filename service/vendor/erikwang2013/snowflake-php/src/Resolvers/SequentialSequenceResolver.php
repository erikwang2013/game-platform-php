<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Classic Snowflake behavior: sequence starts at 0 each millisecond
 * and increments sequentially. Guarantees monotonic IDs within a node.
 */
class SequentialSequenceResolver implements SequenceResolver
{
    /** @var array<int, int> */
    private array $counters = [];

    public function next(int $timestamp, int $maxSequence): ?int
    {
        // Purge old timestamps
        foreach ($this->counters as $ts => $_) {
            if ($ts !== $timestamp) {
                unset($this->counters[$ts]);
            }
        }

        if (!isset($this->counters[$timestamp])) {
            $this->counters[$timestamp] = 0;

            return 0;
        }

        $next = $this->counters[$timestamp] + 1;
        if ($next > $maxSequence) {
            return null;
        }

        $this->counters[$timestamp] = $next;

        return $next;
    }
}
