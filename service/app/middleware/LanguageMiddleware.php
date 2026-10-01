<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use common\Locale;
use common\service\TranslationService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 语言检测中间件（C 端）
 *
 * 检测顺序：`X-Language` 头 → `Accept-Language` 头 → `config('translation.locale')`。
 * 命中后**同时**设两处语言：
 * - `locale()` —— webman/Symfony 翻译器，`trans()` 用它；译文在
 *   `resource/translations/<locale>/messages.php`（**英文句子当键**）
 * - `TranslationService::setLocale()` —— 自建的 **DB 版**翻译服务（`game_translation` 表），
 *   本仓暂无调用点（表也没有种子），保留以免将来接数据库翻译时又要改回来
 *
 * 语言码一律过 `common\Locale::normalize()`：翻译文件按**父目录名**认 locale
 * （`zh-CN`/`zh_CN`/`zh` 不归一就会被当成三个不同 locale、静默回落成英文键名）；
 * 旧实现还把 `X-Language` 直接拿去 `in_array` 比对 `fallback_locale` 里的 `zh-CN` 全码，
 * 于是前端常用的短码 `zh` 会被整条拒掉、跌到 Accept-Language 分支。
 */
class LanguageMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $locale = $this->detectLocale($request);
        locale($locale);
        TranslationService::setLocale($locale);

        return $next($request);
    }

    private function detectLocale(Request $request): string
    {
        // 1. 显式语言头（本树前端拦截器统一带 X-Language）
        $explicit = Locale::normalize((string) $request->header('X-Language', ''));
        if ($explicit !== null) {
            return $explicit;
        }

        // 2. Accept-Language：按顺序试，第一个认得出来的用它
        foreach (explode(',', (string) $request->header('Accept-Language', '')) as $item) {
            $candidate = Locale::normalize(trim(explode(';', $item)[0]));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        // 3. 配置默认（**保持中文**：本树现有响应文案与既有测试断言的都是中文）
        return Locale::normalize((string) config('translation.locale')) ?? 'zh';
    }
}
