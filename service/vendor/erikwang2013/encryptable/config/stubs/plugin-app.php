<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 多框架共用的插件布局主配置：顶层 {@code key} / {@code cipher}。
 *
 * - Webman：置于 {@code config/plugin/{vendor}/{package}/app.php}，读取 {@code config('plugin.{vendor}.{package}.app.*')}。
 * - Laravel / Lumen / ThinkPHP：同路径，由本包在启动时合并或注入到 {@code encryptable.*}。
 *
 * @see https://webman.workerman.net/doc/en/plugin/create.html
 */
return [
    'key' => env('ENCRYPTION_KEY'),
    'cipher' => env('ENCRYPTION_CIPHER', 'aes-256-gcm'),
    'previous_keys' => \Erikwang2013\Encryptable\Support\PreviousKeysParser::parse(env('ENCRYPTION_PREVIOUS_KEYS')),
];
