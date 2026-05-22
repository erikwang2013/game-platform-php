<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Resolvers;

use Erikwang2013\Snowflake\Contracts\SequenceResolver;

/**
 * Uses a random starting point each millisecond to avoid predictable IDs.
 * Tracks in-process usage to prevent collisions within the same millisecond.
 */
class RandomSequenceResolver implements SequenceResolver
{
    /** @var array<int, array<int, bool>> */
    private array $used = [];

    public function next(int $timestamp, int $maxSequence): ?int
    {
        $this->purge($timestamp);

        if (!isset($this->used[$timestamp])) {
            $this->used[$timestamp] = [];
        }

        $slots = $maxSequence + 1;
        if (count($this->used[$timestamp]) >= $slots) {
            return null;
        }

        $retries = $slots * 3;
        for ($i = 0; $i < $retries; $i++) {
            $seq = random_int(0, $maxSequence);
            if (!isset($this->used[$timestamp][$seq])) {
                $this->used[$timestamp][$seq] = true;

                return $seq;
            }
        }

        // Random probes missed — fall back to linear scan to guarantee we find
        // the remaining slot(s) instead of falsely reporting exhaustion.
        for ($seq = 0; $seq <= $maxSequence; $seq++) {
            if (!isset($this->used[$timestamp][$seq])) {
                $this->used[$timestamp][$seq] = true;

                return $seq;
            }
        }

        return null;
    }

    private function purge(int $currentTimestamp): void
    {
        // Remove entries from previous milliseconds to free memory.
        foreach ($this->used as $ts => $_) {
            if ($ts !== $currentTimestamp) {
                unset($this->used[$ts]);
            }
        }
    }
}
