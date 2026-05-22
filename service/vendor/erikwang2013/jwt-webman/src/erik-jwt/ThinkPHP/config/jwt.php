<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

return [
    'secret_key'     => env('JWT.SECRET_KEY', ''),
    'algorithm'      => env('JWT.ALGORITHM', 'HS256'),
    'issuer'         => env('JWT.ISSUER', ''),
    'audience'       => env('JWT.AUDIENCE', ''),
    'leeway'         => (int) env('JWT.LEEWAY', 0),
    'default_expire' => (int) env('JWT.DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT.REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT.STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT.STORAGE_PREFIX', 'jwt_blacklist:'),
        'database' => (int) env('JWT.STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT.ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT.ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT.AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT.CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => [],
    ],
];
