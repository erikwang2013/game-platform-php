<?php
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

/*
 * Redis 连接配置（webman/redis 插件）
 * 所有连接参数均可通过环境变量覆盖，默认值对应本地开发环境。
 * 生产环境建议在 .env 中设置 REDIS_HOST / REDIS_PORT / REDIS_PASSWORD。
 */
return [
    'default' => [
        // 密码：无认证时留空字符串
        'password' => getenv('REDIS_PASSWORD') ?: '',
        // Redis 服务地址
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        // Redis 服务端口
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        // 逻辑数据库编号（0-15），如需切换请在 .env 增加 REDIS_DB 后启用
        'database' => 0,
        // 连接池配置：限流/会话/缓存等高并发场景下复用连接
        'pool' => [
            // 最大连接数
            'max_connections' => 5,
            // 最小空闲连接数
            'min_connections' => 1,
            // 获取连接超时（秒）
            'wait_timeout' => 3,
            // 空闲连接回收时间（秒）
            'idle_timeout' => 60,
            // 心跳间隔（秒）
            'heartbeat_interval' => 50,
        ],
    ]
];
