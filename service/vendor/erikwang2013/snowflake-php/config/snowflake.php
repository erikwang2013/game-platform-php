<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Epoch (in milliseconds)
    |--------------------------------------------------------------------------
    |
    | Starting point for timestamp offset calculation.
    | Default: 2024-01-01 00:00:00 UTC = 1704067200000 ms.
    |
    | A recent epoch maximizes the lifespan of the generator (~69 years
    | from the epoch with 41 timestamp bits).
    |
    */
    'epoch' => 1704067200000,

    /*
    |--------------------------------------------------------------------------
    | Worker ID
    |--------------------------------------------------------------------------
    |
    | Unique identifier for this worker/node in the distributed system.
    | Range: 0 - (2^worker_bits - 1).
    |
    | Use different values per process/server when generating IDs concurrently
    | across multiple nodes.
    |
    */
    'worker_id' => 0,

    /*
    |--------------------------------------------------------------------------
    | Datacenter ID
    |--------------------------------------------------------------------------
    |
    | Unique identifier for the datacenter. Combined with worker_id to form
    | a globally unique node identifier.
    | Range: 0 - (2^datacenter_bits - 1).
    |
    */
    'datacenter_id' => 0,

    /*
    |--------------------------------------------------------------------------
    | Bit Allocation
    |--------------------------------------------------------------------------
    |
    | Control how the 63 data bits are distributed across components.
    | Total must not exceed 63 bits: worker_bits + datacenter_bits + sequence_bits <= 63
    |
    */
    'worker_bits' => 5,
    'datacenter_bits' => 5,
    'sequence_bits' => 12,

    /*
    |--------------------------------------------------------------------------
    | Sequence Resolver
    |--------------------------------------------------------------------------
    |
    | Fully-qualified class name implementing Snowflake\Contracts\SequenceResolver.
    | The default RandomSequenceResolver starts a fresh random sequence in each
    | millisecond for unpredictability.
    |
    */
    'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Tolerance (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Max backward clock movement before throwing ClockDriftException.
    | 0 = strict mode: any backward drift throws immediately.
    | Increase if your infrastructure has minor NTP jitter.
    |
    */
    'clock_tolerance_ms' => 0,

];
