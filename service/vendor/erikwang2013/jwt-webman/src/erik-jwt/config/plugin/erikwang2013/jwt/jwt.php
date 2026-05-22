<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

return [
    'enable'         => true,
    'secret_key'     => getenv('JWT_SECRET_KEY') ?: '',
    'algorithm'      => getenv('JWT_ALGORITHM') ?: 'HS256',
    'issuer'         => getenv('JWT_ISSUER') ?: '',
    'audience'       => getenv('JWT_AUDIENCE') ?: '',
    'leeway'         => (int) (getenv('JWT_LEEWAY') ?: 0),
    'default_expire' => (int) (getenv('JWT_DEFAULT_EXPIRE') ?: 3600),
    'refresh_expire' => (int) (getenv('JWT_REFRESH_EXPIRE') ?: 7200),
    'storage'        => [
        'type'     => getenv('JWT_STORAGE_TYPE') ?: 'file',
        'database' => (int) (getenv('JWT_STORAGE_DATABASE') ?: 0),
        'prefix'   => getenv('JWT_STORAGE_PREFIX') ?: 'jwt_blacklist:',
    ],
    'advanced'       => [
        'retry_attempts'   => (int) (getenv('JWT_ADVANCED_RETRY_ATTEMPTS') ?: 3),
        'retry_delay'      => (int) (getenv('JWT_ADVANCED_RETRY_DELAY') ?: 100),
        'auto_cleanup'     => filter_var(getenv('JWT_ADVANCED_AUTO_CLEANUP') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval' => (int) (getenv('JWT_ADVANCED_CLEANUP_INTERVAL') ?: 3600),
    ],
    'middleware'     => [
        'except' => [],
    ],
];
