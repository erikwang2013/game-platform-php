<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


return [
    'default' => env('CLICKHOUSE_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'driver' => env('CLICKHOUSE_DRIVER', 'http'),
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DB', 'default'),
            'username' => env('CLICKHOUSE_USER', 'default'),
            'password' => env('CLICKHOUSE_PASS', ''),
            'timeout' => env('CLICKHOUSE_TIMEOUT', 30),
        ],
    ],
    'migrations' => [
        'path' => database_path('clickhouse-migrations'),
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => env('CLICKHOUSE_POOL_MIN', 1),
        'max_connections' => env('CLICKHOUSE_POOL_MAX', 8),
        'connection_timeout' => env('CLICKHOUSE_POOL_TIMEOUT', 5),
    ],
    'query_log' => env('CLICKHOUSE_QUERY_LOG', false),
];