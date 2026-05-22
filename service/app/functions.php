<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use common\service\TranslationService;

/**
 * C端业务端公共函数
 */

/**
 * 翻译辅助函数
 *
 * @param string $key 格式: "group.key" 如 "auth.login_success"
 * @param array $replace 参数替换
 * @param string|null $locale 指定语言
 * @return string
 */
function __(string $key, array $replace = [], ?string $locale = null): string
{
    return TranslationService::trans($key, $replace, $locale);
}
