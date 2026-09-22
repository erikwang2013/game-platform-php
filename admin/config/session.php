<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Session\FileSessionHandler;
use Webman\Session\RedisSessionHandler;
use Webman\Session\RedisClusterSessionHandler;

return [

    'type' => 'file', // or redis or redis_cluster

    'handler' => FileSessionHandler::class,

    'config' => [
        'file' => [
            'save_path' => runtime_path() . '/sessions',
        ],
        // 会话后端地址：type=redis / redis_cluster 时生效，可用 REDIS_HOST / REDIS_PORT / REDIS_CLUSTER_NODES 覆盖
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'auth' => '',
            'timeout' => 2,
            'database' => '',
            'prefix' => 'game-platform:session:',
        ],
        'redis_cluster' => [
            'host' => array_map('trim', explode(',', getenv('REDIS_CLUSTER_NODES') ?: '127.0.0.1:7000,127.0.0.1:7001')),
            'timeout' => 2,
            'auth' => '',
            'prefix' => 'game-platform:session:',
        ]
    ],

    'session_name' => 'PHPSID',
    
    'auto_update_timestamp' => false,

    'lifetime' => 7*24*60*60,

    'cookie_lifetime' => 365*24*60*60,

    'cookie_path' => '/',

    'domain' => '',
    
    'http_only' => true,

    'secure' => false,
    
    'same_site' => '',

    'gc_probability' => [1, 1000],

];
