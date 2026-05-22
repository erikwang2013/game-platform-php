<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

return [
    'secret_key'     => env('JWT_SECRET_KEY', ''),
    'algorithm'      => env('JWT_ALGORITHM', 'HS256'),
    'issuer'         => env('JWT_ISSUER', ''),
    'audience'       => env('JWT_AUDIENCE', ''),
    'leeway'         => (int) env('JWT_LEEWAY', 0),
    'default_expire' => (int) env('JWT_DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT_STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT_STORAGE_PREFIX', 'jwt_blacklist:'),
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => [],
    ],
];
