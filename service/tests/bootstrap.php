<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

// 兼容 CLI 和 webman 环境
$worker = $worker ?? null;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// 注册 support\Model 别名 (PHPUnit 环境下需手动注册)
if (!class_exists('support\Model')) {
    class_alias('Illuminate\Database\Eloquent\Model', 'support\Model');
}

// 加载 .env（配置加载前，供 getenv 读取）
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..')->load();
}

// 加载所有配置
\Webman\Config::clear();
support\App::loadAllConfig(['route']);

// 测试环境固定加密密钥：Encryptable 在测试 bootstrap 中读不到 plugin 配置（app.php 未加载），
// 回退到 EnvEncryptableConfig 读取的 ENCRYPTION_KEY；.env 中的开发密钥长度不足
// aes-256-gcm 所需 32 字节。测试数据本就是本地临时数据，固定测试密钥保证确定性。
// Dotenv 已把 .env 值写入 $_ENV/$_SERVER/getenv，三者都要覆盖。
$testKey = '0123456789abcdef0123456789abcdef';
$_ENV['ENCRYPTION_KEY'] = $_SERVER['ENCRYPTION_KEY'] = $testKey;
putenv('ENCRYPTION_KEY=' . $testKey);
putenv('ENCRYPTION_CIPHER=aes-256-gcm');

// 测试专用数据库：默认指向 game_platform_test（可用 DB_DATABASE_TEST 覆盖）。
// Dotenv(mutable) 会覆盖进程环境变量，故在连接初始化时强制覆写，确保测试永不读写开发库。
$dbConfig = config('database');
$dbConfig['connections'][$dbConfig['default']]['database'] = getenv('DB_DATABASE_TEST') ?: 'game_platform_test';
// 测试环境 root 免密（本机 MySQL root 无密码；.env 的 DB_PASSWORD=root 会让测试连不上库）。
// 仅在此处（测试 bootstrap）强制空密码，不影响业务运行。
$dbConfig['connections'][$dbConfig['default']]['password'] = '';

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
