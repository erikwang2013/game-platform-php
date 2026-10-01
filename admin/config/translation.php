<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Multilingual configuration
 */
return [
    // 默认语言：**保持中文** —— 现有调用方与既有测试都在断言中文 message。
    // 用短码（zh/en/ja/ko）是因为翻译文件按**父目录名**认 locale：
    // `resource/translations/<locale>/<domain>.php`（webman support\Translation 的目录扫描）
    'locale' => 'zh',
    // 回落链只挂 en：**键名本身就是英文**，缺译时回落到英文原句（而不是中文）——
    // 这是「英文当键」项目的正确语义；本语言的完整译文由 messages.php 提供。
    'fallback_locale' => ['en'],
    // Folder where language files are stored
    'path' => base_path() . '/resource/translations',
];