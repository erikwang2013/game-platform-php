<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Exceptions;

class TimestampOverflowException extends SnowflakeException
{
    public readonly int $timestampOffset;
    public readonly int $maxOffset;

    public function __construct(int $timestampOffset, int $maxOffset)
    {
        $this->timestampOffset = $timestampOffset;
        $this->maxOffset = $maxOffset;

        parent::__construct(
            sprintf(
                'Timestamp offset %d exceeds maximum %d. The epoch has been exhausted; choose a more recent epoch.',
                $timestampOffset,
                $maxOffset
            )
        );
    }
}
