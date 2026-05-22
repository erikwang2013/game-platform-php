<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 服务器配置
 *
 * listen: HTTP 监听地址和端口（C端业务端使用 8788）
 * context: SSL上下文选项
 * name: worker 进程名称
 * count: worker 进程数（建议 CPU 核心数 * 2）
 * user/worker_user: 进程运行用户
 * reloadable: 文件更新后自动重载
 * reuse_port: 端口复用
 */
return [
    'listen'       => 'http://0.0.0.0:8788',
    'context'      => [],
    'name'         => 'game-platform-service',
    'count'        => cpu_count() * 2,
    'user'         => '',
    'worker_user'  => '',
    'reloadable'   => true,
    'reuse_port'   => true,
];
