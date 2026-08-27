<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\service\TranslationService;

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
    public function availableLanguagesHasFourEntries(): void
    {
        $languages = TranslationService::getAvailableLanguages();
        $this->assertCount(4, $languages);
        foreach (['en-US', 'zh-CN', 'ja-JP', 'ko-KR'] as $lang) {
            $this->assertArrayHasKey($lang, $languages);
            $this->assertArrayHasKey('name', $languages[$lang]);
            $this->assertArrayHasKey('nativeName', $languages[$lang]);
            $this->assertArrayHasKey('icon', $languages[$lang]);
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
        $prop->setAccessible(true);
        $prop->setValue(null, $cache);
    }
}
