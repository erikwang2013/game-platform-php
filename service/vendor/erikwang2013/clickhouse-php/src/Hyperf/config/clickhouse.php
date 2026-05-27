<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


declare(strict_types=1);

return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => (int) env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DB', 'default'),
            'username' => env('CLICKHOUSE_USER', 'default'),
            'password' => env('CLICKHOUSE_PASS', ''),
            'timeout' => (int) env('CLICKHOUSE_TIMEOUT', 30),
        ],
    ],
    'migrations' => [
        'path' => BASE_PATH . '/database/clickhouse-migrations',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => (int) env('CLICKHOUSE_POOL_MIN', 2),
        'max_connections' => (int) env('CLICKHOUSE_POOL_MAX', 16),
        'connection_timeout' => (float) env('CLICKHOUSE_POOL_TIMEOUT', 5.0),
    ],
    'query_log' => (bool) env('CLICKHOUSE_QUERY_LOG', false),
];