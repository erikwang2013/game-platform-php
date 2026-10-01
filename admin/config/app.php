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

use support\Request;

return [
    // 调试开关：**从环境变量读，默认关**。
    // 原先是字面量 `true` 且没有任何读 env 的分支 ⇒ admin/.env.example 的 APP_DEBUG=false
    // 是条死配置，线上跑的一直是 debug 形态：App.php:362 对未捕获异常渲染 `(string) $e`
    // （完整堆栈 + 绝对路径 + vendor 行号）而不是 `$e->getMessage()`，匿名者触发一条
    // 未捕获异常就能拿到目录结构。
    // 必须走 filter_var 而不是 (bool)：Dotenv 传进来的是**字符串**，`(bool) 'false' === true`。
    // 这个写法与 config/plugin/erikwang2013/jwt/jwt.php:32 的 auto_cleanup 同源。
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),
    'error_reporting' => E_ALL,
    'default_timezone' => 'Asia/Shanghai',
    // 应用对外地址（API 文档 baseUrl 等）
    // PUBLIC_APP_URL: compose 注入的键名（.env 中不存在，worker 启动按 .env 重载时不会被覆盖）；
    // 未注入时回退 admin/.env 的 APP_URL
    'url' => getenv('PUBLIC_APP_URL') ?: (getenv('APP_URL') ?: 'http://localhost:8789'),
    // Prometheus /metrics 静态抓取令牌（METRICS_SCRAPE_TOKEN）
    // 留空则静态令牌路径不生效（fail-closed）：只有管理员 JWT 能取指标，不会退化成"空令牌可进"
    'metrics_scrape_token' => (string) getenv('METRICS_SCRAPE_TOKEN'),
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
];
