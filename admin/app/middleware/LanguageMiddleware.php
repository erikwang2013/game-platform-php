<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use common\Locale;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 语言检测中间件（管理端）
 *
 * 与 C 端同名中间件同构，但**只管 webman/Symfony 的翻译器**（`locale()`），
 * 不管自建的 DB 版 `TranslationService` —— 管理端没有任何 DB 翻译的调用点。
 *
 * 检测顺序：`X-Language` 头 → `Accept-Language` 头 → `config('translation.locale')`。
 * 语言码一律过 `common\Locale::normalize()`：翻译文件是按**父目录名**认 locale 的
 * （`resource/translations/<locale>/messages.php`），`zh-CN`/`zh_CN`/`zh` 不归一
 * 就会被当成三个不同 locale，静默查不到译文、回落成英文键名。
 *
 * 响应文案的写法：**英文句子当键**，`trans('User not found')`，
 * 译文在 `resource/translations/{en,zh,ja,ko}/messages.php`；缺键时 Symfony 回键名本身（英文）。
 */
class LanguageMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        locale($this->detectLocale($request));

        return $next($request);
    }

    private function detectLocale(Request $request): string
    {
        // 1. 显式语言头（四棵前端的管理端 api 层与 C 端一致，统一带 X-Language）
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

        // 3. 配置默认（**保持中文**：现有调用方与既有测试都在断言中文 message）
        return Locale::normalize((string) config('translation.locale')) ?? 'zh';
    }
}
