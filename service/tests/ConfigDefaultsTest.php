<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 配置默认值回归：端口/地址集中进配置后，未设 env 时解析值必须与改动前的字面量逐一相等；
 * 设了 env 时必须生效（否则"集中"是假的）。
 *
 * 直接 require 目标配置文件、断言其返回数组：`Config::clear()` + `App::loadAllConfig()`
 * 重载全局配置会重新 include `config/container.php` 造出空容器，丢掉测试引导注册的容器绑定
 * （admin `HashidsServiceTest` 曾因 `Class 'hashids' not found` 变红），不清理又会因
 * `array_replace_recursive` 对列表按下标覆盖而残留元素。DB-free，不连 MySQL/Redis。
 */
class ConfigDefaultsTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        $this->saved = [];
    }

    private function setEnv(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->saved)) {
            $this->saved[$key] = getenv($key);
        }
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            return;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function testSessionRedisDefaultsMatchLegacyLiterals(): void
    {
        $this->setEnv('REDIS_HOST', null);
        $this->setEnv('REDIS_PORT', null);
        $this->setEnv('REDIS_CLUSTER_NODES', null);

        $session = require __DIR__ . '/../config/session.php';
        $this->assertSame('127.0.0.1', $session['config']['redis']['host']);
        $this->assertSame(6379, $session['config']['redis']['port']);
        // 旧字面量是 ['127.0.0.1:7000','127.0.0.1:7001','127.0.0.1:7001']，第三个是重复项，去重后等价
        $this->assertSame(
            ['127.0.0.1:7000', '127.0.0.1:7001'],
            $session['config']['redis_cluster']['host']
        );
    }

    public function testSessionRedisHonoursEnv(): void
    {
        $this->setEnv('REDIS_HOST', '10.9.8.7');
        $this->setEnv('REDIS_PORT', '6390');
        $this->setEnv('REDIS_CLUSTER_NODES', '10.9.8.7:7000, 10.9.8.8:7000'); // 逗号后带空格：钉住 array_map('trim', ...)

        $session = require __DIR__ . '/../config/session.php';
        $this->assertSame('10.9.8.7', $session['config']['redis']['host']);
        $this->assertSame(6390, $session['config']['redis']['port']);
        $this->assertSame(['10.9.8.7:7000', '10.9.8.8:7000'], $session['config']['redis_cluster']['host']);
    }

    public function testScoutHostsDefaultsAndOverride(): void
    {
        $this->setEnv('SCOUT_HOSTS', null);
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame(['http://127.0.0.1:9200'], $scout['elasticsearch']['hosts']);

        $this->setEnv('SCOUT_HOSTS', 'http://es1:9200, http://es2:9200'); // 同上，空格须被 trim
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame(['http://es1:9200', 'http://es2:9200'], $scout['elasticsearch']['hosts']);
    }

    public function testOpenSearchHostDefaultsAndOverride(): void
    {
        $this->setEnv('OPENSEARCH_HTTP_HOST', null);
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame('http://127.0.0.1:9200', $scout['opensearch']['host']);

        $this->setEnv('OPENSEARCH_HTTP_HOST', 'http://os.internal:9200');
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame('http://os.internal:9200', $scout['opensearch']['host']);
    }

    /**
     * `app.debug` 必须真的**读 env**，且**默认关**。
     *
     * 原先是字面量 `true`：`App.php:362` 对未捕获异常渲染 `(string) $e`（完整堆栈 + 绝对路径
     * + vendor 行号）而不是 `$e->getMessage()`，匿名者触发一条未捕获异常就能拿到目录结构。
     * 三条断言各堵一个方向，缺一条这个方法就退化成恒真式：
     *   ① 未设该键 ⇒ false（打回「恒 true」；注意**别**把这条读成「示例文件也是关」，
     *      `service/.env.example:2` 写的是 APP_DEBUG=true，那是另一个决策）
     *   ② 字符串 'false' ⇒ false（打回 `(bool) 'false' === true` 这类转型）
     *   ③ 字符串 'true' ⇒ true（打回写死 false —— 否则「修好了」的代价是运维再也开不起这个开关）
     */
    public function testAppDebugFollowsEnvAndDefaultsOff(): void
    {
        $this->setEnv('APP_DEBUG', null);
        $app = require __DIR__ . '/../config/app.php';
        $this->assertFalse($app['debug'],
            '未设 APP_DEBUG 时 app.debug 必须为 false；恒 true 时未捕获异常会渲染完整堆栈');

        $this->setEnv('APP_DEBUG', 'false');
        $app = require __DIR__ . '/../config/app.php';
        $this->assertFalse($app['debug'],
            "APP_DEBUG='false' 必须解析成 false（(bool) 转型会把它读成 true，Dotenv 给的是字符串）");

        $this->setEnv('APP_DEBUG', 'true');
        $app = require __DIR__ . '/../config/app.php';
        $this->assertTrue($app['debug'],
            "APP_DEBUG='true' 必须解析成 true，否则这个开关名存实亡");
    }
}
