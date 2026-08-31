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
