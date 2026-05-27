<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => getenv('CLICKHOUSE_HOST') ?: 'localhost',
            'port' => getenv('CLICKHOUSE_PORT') ?: 8123,
            'database' => getenv('CLICKHOUSE_DB') ?: 'default',
            'username' => getenv('CLICKHOUSE_USER') ?: 'default',
            'password' => getenv('CLICKHOUSE_PASS') ?: '',
            'timeout' => 30,
        ],
    ],
    'migrations' => [
        'path' => '',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'driver' => 'workerman',
        'min_connections' => 1,
        'max_connections' => 8,
        'connection_timeout' => 5,
    ],
    'query_log' => false,
];