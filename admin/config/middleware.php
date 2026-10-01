<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局中间件配置
 *
 * 以下中间件对所有请求生效，按注册顺序依次执行。
 * 执行顺序: Cors → SecurityFilter → RateLimit → LanguageMiddleware → {路由组中间件} → Controller
 *
 * ⚠ 上面这行是本文件下面那个列表的**转述**，两者必须**逐件一致**：往列表里加/删中间件时，
 * 这行要一起改。`admin/CLAUDE.md` 与 `admin/docs/CLAUDE.md` 的「中间件执行链」又是这行的转述
 * ⇒ 只改文档不改这里 = 半修，下一个 agent 会从本文件读到错的顺序、再"修正"你刚改好的文档。
 * （本条就是这么漏掉 `LanguageMiddleware` 的：列表里有、这行没有。）
 */

return [
    '' => [
        app\middleware\Cors::class,
        app\middleware\SecurityFilter::class,
        app\middleware\RateLimit::class,
        // 语言：从 X-Language / Accept-Language 定 locale，供 trans() 取译文（与 C 端同名中间件同构）
        app\middleware\LanguageMiddleware::class,
    ],
];
