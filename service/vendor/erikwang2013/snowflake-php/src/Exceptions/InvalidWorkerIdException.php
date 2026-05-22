<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Exceptions;

class InvalidWorkerIdException extends SnowflakeException
{
    public readonly int $workerId;
    public readonly int $maxWorkerId;

    public function __construct(int $workerId, int $maxWorkerId)
    {
        $this->workerId = $workerId;
        $this->maxWorkerId = $maxWorkerId;

        parent::__construct(
            sprintf(
                'Worker ID %d exceeds maximum %d (2^bits - 1). Check worker_bits configuration.',
                $workerId,
                $maxWorkerId
            )
        );
    }
}
