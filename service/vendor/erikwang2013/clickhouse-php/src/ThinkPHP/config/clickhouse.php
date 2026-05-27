<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => 'localhost',
            'port' => 8123,
            'database' => 'default',
            'username' => 'default',
            'password' => '',
            'timeout' => 30,
        ],
    ],
    'migrations' => [
        'path' => '',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 8,
        'connection_timeout' => 5,
    ],
    'query_log' => false,
];