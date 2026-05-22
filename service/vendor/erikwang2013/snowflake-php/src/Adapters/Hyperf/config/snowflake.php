<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Epoch (in milliseconds)
    |--------------------------------------------------------------------------
    */
    'epoch' => (int) (\Hyperf\Support\env('SNOWFLAKE_EPOCH', '1704067200000')),

    /*
    |--------------------------------------------------------------------------
    | Worker ID (0 - 31 with default 5 bits)
    |--------------------------------------------------------------------------
    */
    'worker_id' => (int) (\Hyperf\Support\env('SNOWFLAKE_WORKER_ID', '0')),

    /*
    |--------------------------------------------------------------------------
    | Datacenter ID (0 - 31 with default 5 bits)
    |--------------------------------------------------------------------------
    */
    'datacenter_id' => (int) (\Hyperf\Support\env('SNOWFLAKE_DATACENTER_ID', '0')),

    /*
    |--------------------------------------------------------------------------
    | Bit Allocation
    |--------------------------------------------------------------------------
    */
    'worker_bits' => (int) (\Hyperf\Support\env('SNOWFLAKE_WORKER_BITS', '5')),
    'datacenter_bits' => (int) (\Hyperf\Support\env('SNOWFLAKE_DATACENTER_BITS', '5')),
    'sequence_bits' => (int) (\Hyperf\Support\env('SNOWFLAKE_SEQUENCE_BITS', '12')),

    /*
    |--------------------------------------------------------------------------
    | Sequence Resolver
    |--------------------------------------------------------------------------
    */
    'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Tolerance (ms)
    |--------------------------------------------------------------------------
    */
    'clock_tolerance_ms' => (int) (\Hyperf\Support\env('SNOWFLAKE_CLOCK_TOLERANCE_MS', '0')),

];
