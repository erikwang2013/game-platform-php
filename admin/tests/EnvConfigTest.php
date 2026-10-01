<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use support\exception\Handler;
use support\Request;

class EnvConfigTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function env_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env');
    }

    #[Test]
    public function env_example_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env.example');
    }

    #[Test]
    public function getenv_reads_env_variables(): void
    {
        $this->assertNotEmpty(getenv('APP_NAME'), 'APP_NAME 应有值');
        $this->assertNotEmpty(getenv('ADMIN_JWT_SECRET_KEY'), 'ADMIN_JWT_SECRET_KEY 应有值');
        $this->assertNotEmpty(getenv('DB_HOST'), 'DB_HOST 应有值');
    }

    #[Test]
    public function getenv_fallback_pattern_works(): void
    {
        // 存在的变量返回实际值
        $val = getenv('APP_NAME') ?: 'DEFAULT_APP';
        $this->assertNotEquals('DEFAULT_APP', $val);

        // 不存在的变量返回默认值
        $val2 = getenv('THIS_VAR_DOES_NOT_EXIST_XYZ') ?: 'FALLBACK_OK';
        $this->assertEquals('FALLBACK_OK', $val2);
    }

    #[Test]
    public function config_env_keys_exist_in_dotenv(): void
    {
        // 收集 .env 中的键
        $envContent = file_get_contents(__DIR__ . '/../.env');
        preg_match_all('/^([A-Z_][A-Z0-9_]*)=/m', $envContent, $matches);
        $envKeys = array_flip($matches[1]);

        // compose 注入键：键名刻意不出现在 .env（避免 worker 启动时被 .env 重载覆盖），
        // 由根目录 docker-compose.yml 的 environment 传入，此处豁免
        $composeInjected = ['PUBLIC_APP_URL' => true];

        // 检查每个配置文件中的 getenv 键
        $configFiles = glob(__DIR__ . '/../config/*.php');
        $missingKeys = [];

        foreach ($configFiles as $file) {
            $content = file_get_contents($file);
            preg_match_all("/getenv\('([A-Z_][A-Z0-9_]*)'\)/", $content, $m);
            foreach ($m[1] as $key) {
                if (!isset($envKeys[$key]) && !isset($composeInjected[$key])) {
                    $missingKeys[] = basename($file) . ": $key";
                }
            }
        }

        $this->assertEmpty($missingKeys, '以下 env key 在 .env 中缺失: ' . implode(', ', $missingKeys));
    }

    #[Test]
    public function critical_config_types(): void
    {
        $this->assertIsNumeric(getenv('JWT_TTL') ?: 7200, 'JWT_TTL 应为数字');
        $this->assertIsNumeric(getenv('DB_PORT') ?: 3306, 'DB_PORT 应为数字');
        $this->assertIsString(getenv('ADMIN_JWT_SECRET_KEY') ?: 'x', 'ADMIN_JWT_SECRET_KEY 应为字符串');
        $this->assertIsString(getenv('HASHIDS_SALT') ?: 'x', 'HASHIDS_SALT 应为字符串');
    }

    /**
     * `config('app.debug')` 必须**读环境变量**且**默认关**。
     *
     * 原先是字面量 `true`，于是 .env 里的 APP_DEBUG=false 是条死配置 —— 线上一直跑 debug 形态：
     * 未捕获异常经 App.php:362 渲染成 `(string) $e`（完整堆栈 + 绝对路径 + vendor 行号），
     * 而不是 `$e->getMessage()`。这条用例钉三件事，缺一条都能静默退回原状：
     * ① 未设置 ⇒ false（默认关，fail-closed）；② 'true' ⇒ true；③ 'false' ⇒ false。
     * ③ 是重点：`(bool) 'false' === true`，用 (bool) 转型的"修复"会在这里红。
     */
    #[Test]
    public function appDebugReadsEnvAndDefaultsToOff(): void
    {
        $configFile = __DIR__ . '/../config/app.php';
        $original   = getenv('APP_DEBUG');

        try {
            putenv('APP_DEBUG');    // 未设置
            $this->assertFalse((require $configFile)['debug'],
                'APP_DEBUG 未设置时 debug 必须为 false —— 默认开等于把堆栈发给匿名者');

            putenv('APP_DEBUG=true');
            $this->assertTrue((require $configFile)['debug'], "APP_DEBUG=true 应解析为 true");

            putenv('APP_DEBUG=false');
            $this->assertFalse((require $configFile)['debug'],
                "APP_DEBUG=false 必须解析为 false：(bool) 'false' === true，别用 (bool) 转型");
        } finally {
            $original === false ? putenv('APP_DEBUG') : putenv('APP_DEBUG=' . $original);
        }
    }

    /**
     * 上面那条钉的是**配置值**，这条钉它的**后果** —— 关掉 debug 之后客户端还拿不拿得到堆栈。
     *
     * 两条合起来才闭合：配置读对了但渲染层不认这个开关（或框架升级后改了语义）时，
     * 只测配置是绿的而线上仍在漏堆栈。
     *
     * 两个方向都测，缺一即退化成恒真式 —— 若只断言"debug=false 时没堆栈"，
     * 那么一个"无论收到什么都只吐通用文案"的渲染器也能让它绿。
     * debug=true 方向顺带证明探针串真的会被渲染出来（堆栈确实是可达的）。
     */
    #[Test]
    public function exceptionRenderingKeepsStackTraceOutOfTheResponseWhenDebugIsOff(): void
    {
        $request = new Request("GET /admin/v1/dashboard HTTP/1.1\r\nHost: localhost\r\nAccept: application/json\r\n\r\n");
        // 前提：走 JSON 分支。非 JSON 分支是 `nl2br((string)$e)`，由下面同一开关控制，两条路同源
        $this->assertTrue($request->expectsJson(), '前提：本用例走 JSON 渲染分支');

        // 探针串同时带行号与绝对路径：它只会经由异常消息/堆栈进入响应体
        $boom = new \RuntimeException('PROBE_MARKER_' . __LINE__ . ' at ' . __DIR__);

        $off = (new Handler(null, false))->render($request, $boom)->rawBody();
        $this->assertStringNotContainsString('PROBE_MARKER_', $off,
            'debug=false 时响应体不得含异常消息/堆栈 —— 那正是匿名者拿到的目录结构');
        $this->assertStringNotContainsString(__DIR__, $off, 'debug=false 时响应体不得含绝对路径');
        $this->assertArrayNotHasKey('traces', json_decode($off, true) ?: [], 'debug=false 时不得带 traces');

        $on = json_decode((new Handler(null, true))->render($request, $boom)->rawBody(), true) ?: [];
        $this->assertArrayHasKey('traces', $on,
            'debug=true 时应带 traces —— 否则上面那条断言是恒真式，本用例咬不住任何东西');
        $this->assertStringContainsString('PROBE_MARKER_', (string) ($on['traces'] ?? ''),
            'traces 里应能看到探针串，证明探针有效');
    }
}
