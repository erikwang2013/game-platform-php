<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\Locale;
use common\model\Translation;
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
     * 获取可用语言列表（前端语言选择器的数据源；`icon` 是国家码，供 season 旗帜用）
     *
     * **键由 `common\Locale::SUPPORTED` 派生**（与 `install/lang/*.php` 的 13 语集合对齐）：
     * 这里露出带地区的全码（`zh-CN`）是既有 C 端契约（`User::language` 存的就是全码），
     * 而翻译目录用短码 —— 两者由 `common\Locale::normalize()` 打通。
     * 新增语言只落两处：`common\Locale`（短码 + 全码映射）、`resource/translations/<短码>/`。
     */
    public static function getAvailableLanguages(): array
    {
        // 键是**短码**（与 `Locale::SUPPORTED` 同源），输出用 `Locale::fullCode()` 补全码 ——
        // 全码映射只此一处，不再手抄第二份（抄漏的症状是语言选择器里选得到、校验器 422）。
        $meta = [
            'en' => ['name' => 'English', 'nativeName' => 'English', 'icon' => 'us'],
            'zh' => ['name' => 'Chinese (Simplified)', 'nativeName' => '简体中文', 'icon' => 'cn'],
            'ja' => ['name' => 'Japanese', 'nativeName' => '日本語', 'icon' => 'jp'],
            'ko' => ['name' => 'Korean', 'nativeName' => '한국어', 'icon' => 'kr'],
            'ru' => ['name' => 'Russian', 'nativeName' => 'Русский', 'icon' => 'ru'],
            'de' => ['name' => 'German', 'nativeName' => 'Deutsch', 'icon' => 'de'],
            'fr' => ['name' => 'French', 'nativeName' => 'Français', 'icon' => 'fr'],
            'es' => ['name' => 'Spanish', 'nativeName' => 'Español', 'icon' => 'es'],
            'pt' => ['name' => 'Portuguese', 'nativeName' => 'Português', 'icon' => 'pt'],
            'hi' => ['name' => 'Hindi', 'nativeName' => 'हिन्दी', 'icon' => 'in'],
            'ar' => ['name' => 'Arabic', 'nativeName' => 'العربية', 'icon' => 'sa'],
            'bn' => ['name' => 'Bengali', 'nativeName' => 'বাংলা', 'icon' => 'bd'],
            'id' => ['name' => 'Indonesian', 'nativeName' => 'Bahasa Indonesia', 'icon' => 'id'],
        ];

        $languages = [];
        foreach (Locale::supported() as $short) {
            $languages[Locale::fullCode($short)] = $meta[$short];
        }

        return $languages;
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
