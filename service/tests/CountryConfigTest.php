<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\model\CountryConfig;
use support\Db;

/**
 * CountryConfig 单元测试
 * 覆盖: fromLang() 语言前缀映射（迁移后查 game_country_config.lang_prefix，
 * 结果须与旧硬编码映射完全一致）与 methodNames() 兼容解析（旧数组/新规则对象两种格式）
 */
class CountryConfigTest extends TestCase
{
    /**
     * fromLang 为 DB 查询，需迁移后的表结构；方法名解析为纯函数不依赖 DB。
     * CI 中 install.sql 已加载进 game-platform-test，此处仅本地/未迁移时跳过。
     */
    private function requireDb(): void
    {
        try {
            Db::selectOne('SELECT 1');
            $col = Db::selectOne('SHOW COLUMNS FROM game_country_config LIKE "lang_prefix"');
            if (!$col) {
                $this->markTestSkipped('game_country_config.lang_prefix 不存在，请先执行迁移 2026_08_31_localization_compliance');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function fromLangMapsOldHardcodedLanguages(): void
    {
        $this->requireDb();
        // 与迁移前硬编码映射逐一相等（zh->CN ja->JP ko->KR pt->BR hi->IN de->DE en->US）
        $this->assertSame('CN', CountryConfig::fromLang('zh'));
        $this->assertSame('JP', CountryConfig::fromLang('ja'));
        $this->assertSame('KR', CountryConfig::fromLang('ko'));
        $this->assertSame('BR', CountryConfig::fromLang('pt'));
        $this->assertSame('IN', CountryConfig::fromLang('hi'));
        $this->assertSame('DE', CountryConfig::fromLang('de'));
        $this->assertSame('US', CountryConfig::fromLang('en'));
    }

    #[Test]
    public function fromLangTruncatesPrefixAndHandlesUnknown(): void
    {
        $this->requireDb();
        // 'en-US' 截断为 'en' -> US
        $this->assertSame('US', CountryConfig::fromLang('en-US'));
        $this->assertSame('CN', CountryConfig::fromLang('zh-CN'));
        // 未知语言与空串返回空（旧映射同样返回空）
        $this->assertSame('', CountryConfig::fromLang('xx'));
        $this->assertSame('', CountryConfig::fromLang(''));
    }

    /**
     * 一次性证明「无映射」与「DB 故障」现在可区分：
     *   A. fromLang('')  → ''    —— 空串仅此一义：该语言无映射（本分支不查库，恒可判定）
     *   B. fromLang('zh') + DB 故障 → 抛 \Throwable，绝不返回 ''
     *
     * 不依赖本机 MySQL：临时注册一个指向 127.0.0.1:1 的连接（该端口必然拒绝连接），
     * 断言 fromLang 抛出异常而不是返回 ''。DB 起没起都不影响本用例结论。
     */
    #[Test]
    public function fromLangPropagatesDbFailureInsteadOfReturningEmpty(): void
    {
        // A. 「无映射」侧：不查库，空串是唯一合法返回。
        $this->assertSame('', CountryConfig::fromLang(''), '空语言应返回"无映射"哨兵空串');

        // Eloquent 解析连接用的就是这个 resolver（bootstrap 中 bootEloquent 注入）。
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();

        // DatabaseManager 自身没有 addConnection：该方法在 Capsule\Manager 上，且写入的正是
        // DatabaseManager 读取配置的同一个 container。故从共享的 protected static $instance
        // 取回 bootstrap 建成的那个 Capsule 实例。
        $capsule = (new \ReflectionProperty(\Illuminate\Database\Capsule\Manager::class, 'instance'))->getValue();

        // 守卫 1：Eloquent 手里的 manager 必须就是 Capsule 的那个，否则探针注册到了别处。
        $this->assertInstanceOf(
            \Illuminate\Database\Capsule\Manager::class,
            $capsule,
            '未能取到 bootstrap 建成的 Capsule 实例（static $instance 为空），探针连接无法注册'
        );
        $this->assertSame($capsule->getDatabaseManager(), $manager, 'Eloquent 的 resolver 与 Capsule 的 DatabaseManager 不是同一实例');

        $original = $manager->getDefaultConnection();

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => 1,
            'database'  => 'no_such_db',
            'username'  => 'nobody',
            'password'  => 'nobody',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ], 'db_down_probe');
        $manager->setDefaultConnection('db_down_probe');

        // 守卫 2：确认默认连接确实切成探针了。若这里没生效，后面的断言会变成空转（永远真）。
        $this->assertSame('db_down_probe', $manager->getDefaultConnection(), '探针连接未生效，本用例会退化为无意义的空转');

        $thrown = null;
        $result = null;
        try {
            $result = CountryConfig::fromLang('zh');
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            $manager->setDefaultConnection($original);
            $manager->purge('db_down_probe');
        }

        // B. 「DB 故障」侧：必须抛，且绝不能返回 ''（那会与 A 侧混淆）。
        $this->assertInstanceOf(
            \Throwable::class,
            $thrown,
            'DB 故障被静默吞掉：fromLang 返回了 ' . var_export($result, true) . '，与"该语言无映射"不可区分'
        );
    }

    #[Test]
    public function methodNamesParsesOldArrayShape(): void
    {
        $this->assertSame(['stripe', 'paypal'], CountryConfig::methodNames('["stripe","paypal"]'));
        $this->assertSame([], CountryConfig::methodNames('[]'));
    }

    #[Test]
    public function methodNamesParsesNewRuleObjectShape(): void
    {
        $json = '{"stripe":{"enabled":true,"min":"10","max":"5000","fee_percent":"2.9"},"paypal":{"enabled":true}}';
        $this->assertSame(['stripe', 'paypal'], CountryConfig::methodNames($json));
    }

    #[Test]
    public function methodNamesReturnsEmptyOnInvalidInput(): void
    {
        $this->assertSame([], CountryConfig::methodNames('not-json'));
        $this->assertSame([], CountryConfig::methodNames(''));
    }
}
