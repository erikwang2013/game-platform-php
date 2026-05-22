<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Exceptions;

class InvalidDatacenterIdException extends SnowflakeException
{
    public readonly int $datacenterId;
    public readonly int $maxDatacenterId;

    public function __construct(int $datacenterId, int $maxDatacenterId)
    {
        $this->datacenterId = $datacenterId;
        $this->maxDatacenterId = $maxDatacenterId;

        parent::__construct(
            sprintf(
                'Datacenter ID %d exceeds maximum %d (2^bits - 1). Check datacenter_bits configuration.',
                $datacenterId,
                $maxDatacenterId
            )
        );
    }
}
