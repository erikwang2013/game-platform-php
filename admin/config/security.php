<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Security Plugin Configuration
 *
 * 基于 erikwang2013/security-php，覆盖 30 类攻击检测。
 * 存储后端使用 Redis（与项目基础设施一致）。
 */

return [
    'enabled' => true,

    'detectors' => [
        'xss'                => ['enabled' => true, 'mode' => 'block'],
        'sql_injection'      => ['enabled' => true, 'mode' => 'block'],
        'command_injection'  => ['enabled' => true, 'mode' => 'block'],
        'path_traversal'     => ['enabled' => true, 'mode' => 'block'],
        'upload'             => ['enabled' => true, 'mode' => 'block'],
        'ssrf'               => ['enabled' => true, 'mode' => 'block'],
        'xxe'                => ['enabled' => true, 'mode' => 'block'],
        'header_injection'   => ['enabled' => true, 'mode' => 'log'],
        'deserialization'    => ['enabled' => true, 'mode' => 'block'],
        'ldap_injection'     => ['enabled' => true, 'mode' => 'block'],
        'mail_header'        => ['enabled' => true, 'mode' => 'block'],
        'ssti'               => ['enabled' => true, 'mode' => 'log'],
        'nosql_injection'    => ['enabled' => true, 'mode' => 'log'],
        'open_redirect'      => ['enabled' => true, 'mode' => 'block'],
        'jwt_attack'         => ['enabled' => true, 'mode' => 'block'],
        'host_header'        => ['enabled' => true, 'mode' => 'block'],
        'request_smuggling'  => ['enabled' => true, 'mode' => 'block'],
        'graphql_injection'  => ['enabled' => true, 'mode' => 'block'],
        'xpath_injection'    => ['enabled' => true, 'mode' => 'block'],
        'jndi_injection'     => ['enabled' => true, 'mode' => 'block'],
        'ssi_injection'      => ['enabled' => true, 'mode' => 'block'],
        'csv_injection'      => ['enabled' => true, 'mode' => 'block'],
        'data_leak'          => ['enabled' => true, 'mode' => 'block'],
        'prototype_pollution'=> ['enabled' => true, 'mode' => 'block'],
        'websocket'          => ['enabled' => true, 'mode' => 'block'],
        'cors'               => ['enabled' => true, 'mode' => 'block'],
        'dns_rebinding'      => ['enabled' => true, 'mode' => 'block'],
        'http_method' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        ],
        'body_size' => [
            'enabled' => true,
            'mode' => 'block',
            'max_size' => 10485760,
        ],
        'content_type' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_types' => [
                'application/x-www-form-urlencoded',
                'multipart/form-data',
                'application/json',
            ],
        ],
        'csrf_origin' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_origins' => [],
        ],
    ],

    'ip_blacklist' => [
        'enabled' => true,
        'max_attempts' => 5,
        'window_seconds' => 60,
        'ban_duration_seconds' => 900,
    ],

    'storage' => [
        'type' => 'redis',
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'timeout'  => 2.0,
            'password' => null,
            'database' => 0,
            'prefix'   => 'security:',
        ],
    ],

    'block_status_code' => 403,
    'block_message' => 'Request blocked by security policy',

    'log' => [
        'enabled'       => true,
        'channel'       => 'file',
        'path'          => runtime_path('logs/security.log'),
        'max_size'      => 10,
        'dedup_seconds' => 5,
    ],

    'whitelist_ips' => ['127.0.0.1', '::1'],
    'whitelist_fields' => ['_token', '_method', 'csrf_token'],
];
