<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 数据库连接配置
 * 使用 illuminate/database (Laravel Eloquent)
 * 表前缀统一为 erik_
 */
return [
    // 默认连接
    'default' => getenv('DB_CONNECTION') ?: 'mysql',

    'connections' => [
        'mysql' => [
            // 数据库驱动
            'driver' => 'mysql',
            // 数据库主机
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            // 数据库端口
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            // 数据库名
            'database' => getenv('DB_DATABASE') ?: 'open_admin',
            // 用户名
            'username' => getenv('DB_USERNAME') ?: 'root',
            // 密码
            'password' => getenv('DB_PASSWORD') ?: '',
            // 字符集，统一使用 utf8mb4
            'charset' => 'utf8mb4',
            // 排序规则
            'collation' => 'utf8mb4_unicode_ci',
            // 表前缀
            'prefix' => 'erik_',
            // 严格模式
            'strict' => true,
            // 引擎
            'engine' => 'InnoDB',
        ],
    ],
];
