<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Exceptions;

class ClockDriftException extends SnowflakeException
{
    public readonly int $lastTimestamp;
    public readonly int $currentTimestamp;
    public readonly int $driftMs;

    public function __construct(int $lastTimestamp, int $currentTimestamp, int $toleranceMs)
    {
        $this->lastTimestamp = $lastTimestamp;
        $this->currentTimestamp = $currentTimestamp;
        $this->driftMs = $lastTimestamp - $currentTimestamp;

        parent::__construct(
            sprintf(
                'System clock moved backwards by %d ms (last: %d, current: %d). Tolerance: %d ms.',
                $this->driftMs,
                $lastTimestamp,
                $currentTimestamp,
                $toleranceMs
            )
        );
    }
}
