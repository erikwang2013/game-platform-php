<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Snowflake plugin configuration for Webman.
 *
 * Copy or symlink this file to:
 *   {project}/config/plugin/erikwang2013/snowflake-php/app.php
 *
 * Then access via:
 *   $snowflake = new \Erikwang2013\Snowflake\Snowflake(
 *       workerId: (int) config('plugin.erikwang2013.snowflake-php.app.snowflake.worker_id'),
 *       datacenterId: (int) config('plugin.erikwang2013.snowflake-php.app.snowflake.datacenter_id'),
 *   );
 *   $id = $snowflake->id();
 *
 * Or register a singleton in process/bootstrap.php:
 *   Worker::$container->add(\Erikwang2013\Snowflake\Snowflake::class, function () {
 *       return \Erikwang2013\Snowflake\Snowflake::fromConfig(
 *           config('plugin.erikwang2013.snowflake-php.app.snowflake')
 *       );
 *   });
 */

return [
    'enable' => true,
    'snowflake' => [
        'epoch' => 1704067200000,
        'worker_id' => getenv('SNOWFLAKE_WORKER_ID') ?: 0,
        'datacenter_id' => getenv('SNOWFLAKE_DATACENTER_ID') ?: 0,
        'worker_bits' => 5,
        'datacenter_bits' => 5,
        'sequence_bits' => 12,
        'sequence_resolver' => \Erikwang2013\Snowflake\Resolvers\SequentialSequenceResolver::class,
        'clock_tolerance_ms' => 0,
    ],
];
