<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use common\service\TranslationService;
use support\Request;
use Webman\MiddlewareInterface;
use Webman\Http\Response;

/**
 * 语言检测中间件
 *
 * 检测顺序:
 * 1. 请求头 X-Language
 * 2. 用户语言偏好（已认证用户从数据库读取）
 * 3. Accept-Language 头
 * 4. 默认 en-US
 */
class LanguageMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $locale = $this->detectLocale($request);
        TranslationService::setLocale($locale);

        return $next($request);
    }

    private function detectLocale(Request $request): string
    {
        // 1. 显式指定的语言头
        $headerLang = $request->header('X-Language', '');
        if ($headerLang && in_array($headerLang, ['en-US', 'zh-CN', 'ja-JP', 'ko-KR'])) {
            return $headerLang;
        }

        // 2. Accept-Language 头
        $acceptLang = $request->header('Accept-Language', '');
        if ($acceptLang) {
            $locales = explode(',', $acceptLang);
            foreach ($locales as $locale) {
                $code = trim(explode(';', $locale)[0]);
                if (str_starts_with($code, 'zh')) return 'zh-CN';
                if (str_starts_with($code, 'ja')) return 'ja-JP';
                if (str_starts_with($code, 'ko')) return 'ko-KR';
                if (str_starts_with($code, 'en')) return 'en-US';
            }
        }

        // 3. 默认英文
        return 'en-US';
    }
}
