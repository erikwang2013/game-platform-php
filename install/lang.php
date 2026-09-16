<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 一键安装向导 — 多语言加载器
 */

/**
 * 支持的界面语言（与 docs/ 一致）；键为语言代码，值为母语名称
 */
function installer_languages(): array
{
    return [
        'zh-CN' => '简体中文',
        'en' => 'English',
        'ko' => '한국어',
        'ru' => 'Русский',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'pt' => 'Português',
        'hi' => 'हिन्दी',
        'ar' => 'العربية',
        'bn' => 'বাংলা',
        'id' => 'Bahasa Indonesia',
        'ja' => '日本語',
    ];
}

/**
 * 当前界面语言。优先级: URL ?lang= → cookie → 浏览器 Accept-Language → 默认 zh-CN
 */
function installer_current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $available = array_keys(installer_languages());

    if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
        $lang = $_GET['lang'];
        if (!headers_sent()) {
            setcookie('installer_lang', $lang, ['expires' => time() + 86400 * 7, 'path' => '/', 'samesite' => 'Lax']);
        }
    } elseif (isset($_COOKIE['installer_lang']) && is_string($_COOKIE['installer_lang']) && in_array($_COOKIE['installer_lang'], $available, true)) {
        $lang = $_COOKIE['installer_lang'];
    } else {
        $lang = negotiate_lang((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $available);
    }

    return $lang;
}

/**
 * 解析 Accept-Language，返回匹配到的语言代码（无匹配时 zh-CN）
 *
 * @param string[] $available
 */
function negotiate_lang(string $header, array $available): string
{
    $aliases = [
        'zh' => 'zh-CN', 'zh-cn' => 'zh-CN', 'zh-hans' => 'zh-CN',
        'zh-tw' => 'zh-CN', 'zh-hant' => 'zh-CN',
        'pt-br' => 'pt', 'pt-pt' => 'pt',
        'in' => 'id',
        'iw' => 'ar',
    ];
    $lowerToCode = [];
    foreach ($available as $code) {
        $lowerToCode[strtolower($code)] = $code;
    }

    $best = '';
    $bestQ = -1.0;
    foreach (explode(',', $header) as $part) {
        $bits = explode(';', trim($part));
        $tag = strtolower(trim($bits[0]));
        if ($tag === '') {
            continue;
        }
        $q = 1.0;
        foreach (array_slice($bits, 1) as $bit) {
            $bit = trim($bit);
            if (str_starts_with($bit, 'q=')) {
                $q = (float) substr($bit, 2);
            }
        }
        $mapped = $aliases[$tag] ?? $lowerToCode[$tag] ?? $lowerToCode[explode('-', $tag)[0]] ?? '';
        if ($mapped !== '' && $q > $bestQ) {
            $best = $mapped;
            $bestQ = $q;
        }
    }

    return $best !== '' ? $best : 'zh-CN';
}

/**
 * 取翻译文案；:param 占位符按 $params 插值，键缺失时原样返回键名
 */
function t(string $key, array $params = []): string
{
    static $strings = null;
    if ($strings === null) {
        $file = __DIR__ . '/lang/' . installer_current_lang() . '.php';
        $strings = is_readable($file) ? (require $file) : [];
    }
    $text = $strings[$key] ?? $key;
    if ($params) {
        $replace = [];
        foreach ($params as $k => $v) {
            $replace[':' . $k] = (string) $v;
        }
        $text = strtr($text, $replace);
    }
    return $text;
}
