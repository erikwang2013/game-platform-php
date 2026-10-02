<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\Locale;
use common\service\TranslationService;

/**
 * TranslationService 单元测试
 * 覆盖: locale 存取、可用语言列表、trans 解析/回退/参数替换、缓存清理
 */
class TranslationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        TranslationService::clearCache();
        TranslationService::setLocale('en-US');
    }

    #[Test]
    public function setAndGetLocale(): void
    {
        TranslationService::setLocale('zh-CN');
        $this->assertSame('zh-CN', TranslationService::getLocale());
    }

    #[Test]
    public function availableLanguagesCoverEverySupportedLocale(): void
    {
        $languages = TranslationService::getAvailableLanguages();

        // 与 `common\Locale::SUPPORTED` 必须**逐项对齐**：两处枚举各写一份，漂移了就会
        // 「语言选择器里选得到、翻译目录里没有」或反之 —— 前者更糟：选了却查不到译文，
        // 界面静默回落到英文键名。
        $normalized = array_values(array_filter(array_map(
            static fn ($code) => Locale::normalize($code),
            array_keys($languages)
        )));
        sort($normalized);
        $supported = Locale::supported();
        sort($supported);

        $this->assertSame($supported, $normalized, '语言全码表归一后必须等于 Locale::supported()');
        $this->assertCount(13, $languages, '当前支持 13 种语言（与 install/lang/ 的语族对齐）');

        foreach ($languages as $code => $meta) {
            $this->assertArrayHasKey('name', $meta, $code);
            $this->assertArrayHasKey('nativeName', $meta, $code);
            $this->assertArrayHasKey('icon', $meta, $code);
        }
    }

    #[Test]
    public function transReturnsKeyWhenMalformed(): void
    {
        $this->assertSame('no_dot_key', TranslationService::trans('no_dot_key'));
    }

    #[Test]
    public function transReturnsKeyWhenMissingFromCache(): void
    {
        self::injectCache([
            'zh-CN' => ['auth' => ['login_success' => '登录成功']],
            'en-US' => ['auth' => ['login_success' => 'Login success']],
        ]);

        $this->assertSame('auth.missing_key', TranslationService::trans('auth.missing_key', [], 'zh-CN'));
    }

    #[Test]
    public function transTranslatesWithLocale(): void
    {
        self::injectCache([
            'zh-CN' => ['auth' => ['login_success' => '登录成功']],
            'en-US' => ['auth' => ['login_success' => 'Login success']],
        ]);

        $this->assertSame('登录成功', TranslationService::trans('auth.login_success', [], 'zh-CN'));
        $this->assertSame('Login success', TranslationService::trans('auth.login_success', [], 'en-US'));
    }

    #[Test]
    public function transFallsBackToEnglish(): void
    {
        self::injectCache([
            'en-US' => ['auth' => ['login_success' => 'Login success']],
        ]);

        // ja-JP 无翻译时回退 en-US
        $this->assertSame('Login success', TranslationService::trans('auth.login_success', [], 'ja-JP'));
    }

    #[Test]
    public function transAppliesReplacements(): void
    {
        self::injectCache([
            'en-US' => ['withdraw' => ['completed' => '{amount} tokens sent']],
        ]);

        $this->assertSame(
            '100 tokens sent',
            TranslationService::trans('withdraw.completed', ['{amount}' => '100'], 'en-US')
        );
    }

    #[Test]
    public function clearCacheResetsInMemoryCache(): void
    {
        self::injectCache(['en-US' => ['auth' => ['login_success' => 'Login success']]]);
        TranslationService::clearCache();
        // 缓存清空后 trans 走 DB/Redis；此处只验证不抛异常（DB 不可用则返回 key）
        $result = TranslationService::trans('auth.login_success', [], 'en-US');
        $this->assertIsString($result);
    }

    private static function injectCache(array $cache): void
    {
        $prop = new \ReflectionProperty(TranslationService::class, 'cache');
        $prop->setValue(null, $cache);
    }
}
