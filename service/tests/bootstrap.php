<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

// 兼容 CLI 和 webman 环境
$worker = $worker ?? null;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// support/helpers.php（validator()/jwt_wrapper()）不在 composer files 自动加载里，
// 运行期由 webman 的 support/bootstrap.php 载入；PHPUnit 下没有这层引导，必须显式 require。
require_once dirname(__DIR__) . '/support/helpers.php';

// 注册 support\Model 别名 (PHPUnit 环境下需手动注册)
if (!class_exists('support\Model')) {
    class_alias('Illuminate\Database\Eloquent\Model', 'support\Model');
}

// 加载 .env（配置加载前，供 getenv 读取）
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..')->load();
}

// 测试环境固定 JWT 密钥：.env 中的占位符会触发 jwt 配置启动守卫（拒绝启动）。
// 与 ENCRYPTION_KEY 同模式，在配置加载前注入，规避占位符校验。
$testJwt = 'test-jwt-secret-0123456789abcdef-test-jwt-secret';
$_ENV['SERVICE_JWT_SECRET_KEY'] = $_SERVER['SERVICE_JWT_SECRET_KEY'] = $testJwt;
putenv('SERVICE_JWT_SECRET_KEY=' . $testJwt);

// 测试环境固定 hashids 盐值：.env 中的占位符同样触发 hashids 配置启动守卫。
$testHashSalt = 'test-hashids-salt-0123456789';
$testAltSalt = 'test-hashids-alt-salt-0123456789';
$_ENV['HASHIDS_SALT'] = $_SERVER['HASHIDS_SALT'] = $testHashSalt;
putenv('HASHIDS_SALT=' . $testHashSalt);
$_ENV['HASHIDS_ALT_SALT'] = $_SERVER['HASHIDS_ALT_SALT'] = $testAltSalt;
putenv('HASHIDS_ALT_SALT=' . $testAltSalt);

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

// 测试专用数据库：默认指向 game-platform-test（可用 DB_DATABASE_TEST 覆盖）。
// ⚠ 这里改写的只是 $dbConfig 这个**局部副本**：Webman\Config 没有 set()，config('database')
//   全程仍是开发库的值，动不了它。真正让测试落到测试库的是下面「先烧守卫、再由测试库
//   Capsule 最后 setAsGlobal()」那段**顺序** —— 不是这行赋值（2026-10-02 实测：只改这行时
//   套件实际仍连 game-platform）。
$dbConfig = config('database');
$dbConfig['connections'][$dbConfig['default']]['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
// 口令沿用 config('database')（.env）的值，此处不再覆写：旧口径「本机 root 无密码、故强制空密码」
// 自 2026-09-17 root 启用口令后失效，后果是全部连库用例静默 skip，并被长期误读成「本机 MySQL 失效」。

// 初始化 Eloquent 与 support\Db，全程钉在测试库上。两处会抢全局解析器，都要处理：
//
// 一、support\bootstrap\Database::start 按 config('database')（= 开发库，上面动不了）装了一个
//   Capsule 并 setAsGlobal()。所以下面手工重建一个指向测试库的 Capsule 覆盖掉
//   （与 support\Db 共享同一 static 实例）；MySQL 不可用时由各测试用例自行跳过。
//
// 二、更隐蔽的是 vendor/webman/database/src/Initializer.php：文件尾部是一句裸露的
//   `Initializer::init(config('database', []))`，在 include 那一刻就执行，而 support/Db.php:21
//   正是 require_once 这个文件。而 support\Db 是**懒加载**的 —— 第一个碰它的用例发生在
//   bootstrap **之后**，那次 init 读到的是**未改写**的开发库配置，它消耗掉一次性 $initialized
//   守卫并用 setAsGlobal() 把全局解析器改指开发库 ⇒ 上面那个测试库 Capsule 白建，整套连库
//   用例（含清理逻辑）全落到开发库。2026-10-02 实测：套件内 config('database')、
//   Db::connection()->getDatabaseName()、模型连接三处都是 game-platform。
//   ⚠ 不能改成"直接调 Initializer::init($dbConfig)"：一提类名就会 autoload 该文件，文件尾那次
//   init(开发库) 先跑并吃掉守卫，显式调用只会静默空转，真正落笔的仍是开发库。
//   解法＝抢在下面那个测试库 Capsule **之前**把该文件整个 include 掉，让开发库那次 init 先发生、
//   先烧掉守卫，再由测试库 Capsule 最后 setAsGlobal() 落笔。此后任何 support\Db autoload 都空转。
//   ⚠ **顺序是关键**：把下面这行 require_once 挪到 Capsule 之后，开发库会重新赢
//   （钉子：tests/DatabaseIsolationTest.php，变异就是把这一行挪到 Capsule 之后）。
require_once __DIR__ . '/../vendor/webman/database/src/Initializer.php';

$capsule = new \Illuminate\Database\Capsule\Manager();
foreach ($dbConfig['connections'] as $name => $connection) {
    $capsule->addConnection($connection, $name);
}
$capsule->getDatabaseManager()->setDefaultConnection($dbConfig['default']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
