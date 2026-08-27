<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

// 兼容 CLI 和 webman 环境
$worker = $worker ?? null;

require_once __DIR__ . '/../vendor/autoload.php';

// 注册 support\Model 别名 (PHPUnit 环境下需手动注册)
if (!class_exists('support\Model')) {
    class_alias('Illuminate\Database\Eloquent\Model', 'support\Model');
}

// 加载 .env
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    if (method_exists('Dotenv\Dotenv', 'createUnsafeMutable')) {
        \Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..')->load();
    } else {
        \Dotenv\Dotenv::createMutable(__DIR__ . '/..')->load();
    }
}

// 测试环境固定 JWT 密钥：.env 中的占位符会触发 jwt 配置启动守卫（拒绝启动）。
// 与 ENCRYPTION_KEY 同模式，在配置加载前注入，规避占位符校验。
$testJwt = 'test-jwt-secret-0123456789abcdef-test-jwt-secret';
$_ENV['ADMIN_JWT_SECRET_KEY'] = $_SERVER['ADMIN_JWT_SECRET_KEY'] = $testJwt;
putenv('ADMIN_JWT_SECRET_KEY=' . $testJwt);

// 加载所有配置
\Webman\Config::clear();
support\App::loadAllConfig(['route']);

// 测试专用数据库：默认指向 game-platform-test（可用 DB_DATABASE_TEST 覆盖）。
// config/database.php 支持 getenv 覆盖，但 Dotenv(mutable) 会覆盖进程环境变量，故在连接初始化时强制覆写，
// 确保测试永不读写开发库。
$dbConfig = config('database');
$dbConfig['connections'][$dbConfig['default']]['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';

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

// 初始化 Eloquent 与 support\Db。
// 注意：不能用 Webman\Database\Initializer::init()——support\Db 首次被 autoload 时
// 会 require Initializer.php，其文件尾部立即调用 init() 消耗一次性 $initialized 守卫；
// 随后 support\bootstrap\Database::start 又用未设置默认连接的裸 Capsule 覆盖全局实例，
// 使后续 init() 全部空转，默认连接 'default' 缺失。这里在 bootstrap 之后统一用裸 Capsule
// 重建（与 support\Db 共享同一 static 实例）；MySQL 不可用时由各测试用例自行跳过。
$capsule = new \Illuminate\Database\Capsule\Manager();
foreach ($dbConfig['connections'] as $name => $connection) {
    $capsule->addConnection($connection, $name);
}
$capsule->getDatabaseManager()->setDefaultConnection($dbConfig['default']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
