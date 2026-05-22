<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

// 兼容 CLI 和 webman 环境
$worker = $worker ?? null;

require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeMutable')) {
        \Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..')->load();
    } else {
        \Dotenv\Dotenv::createMutable(__DIR__ . '/..')->load();
    }
}

// 加载所有配置
\Webman\Config::clear();
support\App::loadAllConfig(['route']);

// 加载 autoload 文件
foreach (config('autoload.files', []) as $file) {
    include_once $file;
}
foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        if (!is_array($project)) continue;
        foreach ($project['autoload']['files'] ?? [] as $file) {
            include_once $file;
        }
    }
    foreach ($projects['autoload']['files'] ?? [] as $file) {
        include_once $file;
    }
}

// 运行 Bootstrap 插件（注册全局函数 hashids/jwt/captcha 等）
foreach (config('bootstrap', []) as $className) {
    if (class_exists($className)) {
        $className::start($worker);
    }
}
foreach (config('plugin', []) as $firm => $projects) {
    foreach ($projects as $name => $project) {
        if (!is_array($project)) continue;
        foreach ($project['bootstrap'] ?? [] as $className) {
            if (class_exists($className)) {
                $className::start($worker);
            }
        }
    }
    foreach ($projects['bootstrap'] ?? [] as $className) {
        if (class_exists($className)) {
            $className::start($worker);
        }
    }
}
