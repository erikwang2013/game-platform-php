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
    // 原先是字面量 `true` 且没有任何读 env 的分支 ⇒ `.env` 里写 APP_DEBUG=false 也压不住它，
    // 未捕获异常一律走 debug 形态：App.php:362 渲染 `(string) $e`
    // （完整堆栈 + 绝对路径 + vendor 行号）而不是 `$e->getMessage()`，匿名者触发一条
    // 未捕获异常就能拿到目录结构。
    // 必须走 filter_var 而不是 (bool)：Dotenv 传进来的是**字符串**，`(bool) 'false' === true`。
    // 这个写法与 config/plugin/erikwang2013/jwt/jwt.php:32 的 auto_cleanup 同源。
    // ⚠ 这条**不等于**本应用默认已关：`service/.env.example:2` 自己写的就是 `APP_DEBUG=true`
    //   （admin 的示例是 false，两边不一样）⇒ 照示例装机仍是开着的。改示例＝改新装机的部署
    //   默认值，属策略不属缺陷，已报 lead 由用户拍板，此处不动。
    // ⚠ 未设该键时本行取 false（原先恒 true），对不带该键的既有部署是**收紧**：
    //   要保留调试输出，就在 .env 里显式写 APP_DEBUG=true。
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),
    'error_reporting' => E_ALL,
    'default_timezone' => 'Asia/Shanghai',
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
];
