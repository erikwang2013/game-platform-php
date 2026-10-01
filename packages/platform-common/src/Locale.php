<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

/**
 * 语言代码归一 —— **跨树唯一实现**（admin 与 service 的语言中间件都用它）。
 *
 * 项目的语言码有四种写法混着用：Accept-Language 的 `zh-CN`、部分配置里的 `zh_CN`、
 * 前端偏好与**翻译目录名**用的短码 `zh`、以及 `zh-Hans-CN` 这类带脚本的变体。
 * 而翻译文件是按**父目录名**当 locale 认的（webman `support\Translation` 的目录扫描：
 * `resource/translations/<locale>/<domain>.php`）⇒ 不先归一，同一个语言会被当成三个
 * 不同 locale，查不到译文就静默回落成英文键名（界面看着像"没翻译"，实际是 locale 没对上）。
 */
class Locale
{
    /**
     * 支持的语言（**与 `install/lang/*.php` 的 13 语集合逐项对齐**，短码）：
     * en 英 / zh 中 / ja 日 / ko 韩 / ru 俄 / de 德 / fr 法 / es 西 / pt 葡 /
     * hi 印地 / ar 阿拉伯 / bn 孟加拉 / id 印尼。
     * 新增语言要同时落三处：这里、翻译目录 `resource/translations/<code>/`、
     * 以及 `TranslationService::getAvailableLanguages()`（前端语言选择器读它）。
     */
    private const SUPPORTED = ['en', 'zh', 'ja', 'ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id'];

    /**
     * 任意写法 → 受支持的短码；认不出来回 null（调用方自行决定回落）。
     * `zh-Hans-CN` / `zh_CN` / `zh-CN` / `zh;q=0.9` / `ZH` 一律得 `zh`。
     */
    public static function normalize(?string $raw): ?string
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === '') {
            return null;
        }

        $primary = preg_split('/[-_;,]/', $raw)[0] ?? '';

        return in_array($primary, self::SUPPORTED, true) ? $primary : null;
    }

    /** @return string[] */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    /**
     * 入参校验白名单：**短码 + C 端全码**两种写法的并集。
     *
     * 校验器要挡住的是"不支持的语言"，不是"写法不对"——`LanguageController` 收
     * `zh-CN`、`UserController::updateProfile` 收客户端偏好，两种写法都在线上跑。
     * 用 `Locale::accepted()` 而不是再手写一份 `en-US,zh-CN,…`：手写的那份已经
     * 漂移过一次（13 语言落地时两处白名单还停在 4 种），而白名单漏项的症状是
     * **选了语言却 422**，不是静默回落，用户直接看到报错。
     *
     * @return string[] 如 ['en','en-US','zh','zh-CN',…]
     */
    public static function accepted(): array
    {
        $accepted = [];
        foreach (self::SUPPORTED as $short) {
            $accepted[] = $short;
            $accepted[] = self::fullCode($short);
        }

        return $accepted;
    }

    /** 短码 → C 端全码（`zh` ⇒ `zh-CN`；未知短码原样返回） */
    public static function fullCode(string $short): string
    {
        return self::FULL_CODES[$short] ?? $short;
    }

    /** 与 `TranslationService::getAvailableLanguages()` 的键逐项一致 */
    private const FULL_CODES = [
        'en' => 'en-US',
        'zh' => 'zh-CN',
        'ja' => 'ja-JP',
        'ko' => 'ko-KR',
        'ru' => 'ru-RU',
        'de' => 'de-DE',
        'fr' => 'fr-FR',
        'es' => 'es-ES',
        'pt' => 'pt-PT',
        'hi' => 'hi-IN',
        'ar' => 'ar-SA',
        'bn' => 'bn-BD',
        'id' => 'id-ID',
    ];
}
