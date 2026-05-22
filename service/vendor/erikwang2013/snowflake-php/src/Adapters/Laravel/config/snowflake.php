<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Epoch (in milliseconds)
    |--------------------------------------------------------------------------
    |
    | Default: 2024-01-01 00:00:00 UTC = 1704067200000 ms.
    | A recent epoch maximizes generator lifespan (~69 years from epoch).
    |
    */
    'epoch' => env('SNOWFLAKE_EPOCH', 1704067200000),

    /*
    |--------------------------------------------------------------------------
    | Worker ID
    |--------------------------------------------------------------------------
    |
    | Unique identifier for this node (0 - 31 with default 5 bits).
    | Set via environment variable or server-specific config.
    |
    */
    'worker_id' => env('SNOWFLAKE_WORKER_ID', 0),

    /*
    |--------------------------------------------------------------------------
    | Datacenter ID
    |--------------------------------------------------------------------------
    |
    | Unique identifier for the datacenter (0 - 31 with default 5 bits).
    |
    */
    'datacenter_id' => env('SNOWFLAKE_DATACENTER_ID', 0),

    /*
    |--------------------------------------------------------------------------
    | Bit Allocation
    |--------------------------------------------------------------------------
    |
    | Total must not exceed 63 bits.
    |
    */
    'worker_bits' => env('SNOWFLAKE_WORKER_BITS', 5),
    'datacenter_bits' => env('SNOWFLAKE_DATACENTER_BITS', 5),
    'sequence_bits' => env('SNOWFLAKE_SEQUENCE_BITS', 12),

    /*
    |--------------------------------------------------------------------------
    | Sequence Resolver
    |--------------------------------------------------------------------------
    |
    | FQCN implementing Snowflake\Contracts\SequenceResolver.
    |
    */
    'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Tolerance (ms)
    |--------------------------------------------------------------------------
    |
    | Max backward clock movement before throwing ClockDriftException.
    | 0 = strict mode.
    |
    */
    'clock_tolerance_ms' => env('SNOWFLAKE_CLOCK_TOLERANCE_MS', 0),

];
