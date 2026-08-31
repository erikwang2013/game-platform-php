<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\model\Translation;
use support\Redis;

/**
 * 国际化翻译服务
 *
 * 从数据库加载翻译文本，Redis 缓存 1 小时。
 * 支持分组和参数替换。
 */
class TranslationService
{
    private static ?array $cache = null;
    private static string $locale = 'en-US';
    private const CACHE_KEY = 'i18n:translations';
    private const CACHE_TTL = 3600;

    /**
     * 设置当前语言
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    /**
     * 获取当前语言
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * 获取可用语言列表
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'en-US' => ['name' => 'English', 'nativeName' => 'English', 'icon' => 'us'],
            'zh-CN' => ['name' => 'Chinese (Simplified)', 'nativeName' => '简体中文', 'icon' => 'cn'],
            'ja-JP' => ['name' => 'Japanese', 'nativeName' => '日本語', 'icon' => 'jp'],
            'ko-KR' => ['name' => 'Korean', 'nativeName' => '한국어', 'icon' => 'kr'],
        ];
    }

    /**
     * 翻译文本
     *
     * @param string $key 格式: "group.key" 如 "auth.login_success"
     * @param array $replace 参数替换，如 ['{name}' => 'John']
     * @param string|null $locale 指定语言，null 使用当前设置的语言
     * @return string
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale;

        // 解析 group.key
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return $key;
        }

        [$group, $item] = $parts;

        // 从缓存或数据库加载翻译
        self::loadTranslations();

        $value = self::$cache[$locale][$group][$item] ?? null;

        if ($value === null) {
            // 回退到英文
            $value = self::$cache['en-US'][$group][$item] ?? $key;
        }

        // 参数替换
        if (!empty($replace)) {
            $value = strtr($value, $replace);
        }

        return $value;
    }

    /**
     * 加载翻译文本到内存缓存
     */
    private static function loadTranslations(): void
    {
        if (self::$cache !== null) {
            return;
        }

        // 先从 Redis 加载
        try {
            $cached = Redis::get(self::CACHE_KEY);
            if ($cached) {
                self::$cache = json_decode($cached, true);
                return;
            }
        } catch (\Throwable $e) {
            // Redis 不可用时回退到数据库直接查询
        }

        // 从数据库加载
        self::loadFromDatabase();
    }

    /**
     * 从数据库加载所有翻译
     */
    private static function loadFromDatabase(): void
    {
        self::$cache = [];

        try {
            $translations = Translation::all();
            foreach ($translations as $t) {
                self::$cache[$t->lang_code][$t->group][$t->key] = $t->value;
            }
        } catch (\Throwable $e) {
            // 数据库不可用时使用空缓存
            self::$cache = [];
            return;
        }

        // 写入 Redis
        try {
            Redis::setex(self::CACHE_KEY, self::CACHE_TTL, json_encode(self::$cache, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // 忽略 Redis 错误
        }
    }

    /**
     * 清除翻译缓存
     */
    public static function clearCache(): void
    {
        self::$cache = null;
        try {
            Redis::del(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // 忽略
        }
    }
}
