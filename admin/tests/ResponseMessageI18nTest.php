<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 响应文案国际化的**常驻钉子**（无数据库依赖，纯静态扫描）。
 *
 * 为什么要有它：文案走 `trans('English sentence')` 之后，正确性不再由 PHP 语法保证——
 * 键写错一个字符、或新写的 `fail('中文')` 忘了包 `trans`，两种错都**静默**：
 * 前者回落成英文键名（界面看着像没翻译），后者在 13 种语言里恒定显示中文。
 * 代码评审看不出来，跑用例也全绿。这里把三件事钉死：
 *
 * 1. 代码里出现的每个 `trans('…')` 键，在 **zh 与全部 12 个语言表**里都存在；
 * 2. `fail()` / `success()` 的 message 实参**一律**经过 `trans()`（字面量绕过即失败）；
 * 3. 13 个语言表的键集合**逐项相等**（缺一个就是那种语言的用户看到英文键名）。
 */
class ResponseMessageI18nTest extends TestCase
{
    /** 本棵树的根（tests/ 的上一级） */
    private const ROOT = __DIR__ . '/..';

    /** 语言目录 = `common\Locale::SUPPORTED`；zh 是权威键表，en 刻意空表（键名即英文） */
    private const LOCALES = ['en', 'zh', 'ja', 'ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id'];

    /**
     * `trans(self::常量)` 形式的键：常量名 => 常量值。
     * 扫描器认不出常量取值，所以在此**显式登记**；改了常量值必须同步改这里，
     * 否则下面 `keysAreCoveredByEveryLocale` 会因为表里查不到而变红（fail-loud，不是静默漏）。
     */
    private const CONSTANT_KEYS = [];

    /** 递归列出 app/ 下所有 php 文件 */
    private static function phpFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);   // 顺序稳定，失败信息可复现

        return $files;
    }

    private static function tokens(string $file): array
    {
        // 去掉空白与注释：它们在判"实参是不是一个调用"时没有意义，去掉后下标运算简单得多
        return array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
    }

    /** @return array<string,string> 相对路径 => 该文件里 trans('…') 的字面量键 */
    private static function literalTransKeys(): array
    {
        $found = [];
        foreach (self::phpFiles() as $file) {
            $tokens = self::tokens($file);
            foreach ($tokens as $i => $t) {
                if (!is_array($t) || $t[0] !== T_STRING || $t[1] !== 'trans') {
                    continue;
                }
                if (!($tokens[$i + 1] ?? null) || $tokens[$i + 1] !== '(') {
                    continue;
                }
                $arg = $tokens[$i + 2] ?? null;
                if (is_array($arg) && $arg[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $found[substr($file, strlen(self::ROOT) + 1)][] = trim($arg[1], "'\"");
                }
            }
        }

        return $found;
    }

    /** @return string[] 某个语言表的键 */
    private static function localeKeys(string $locale): array
    {
        $path = self::ROOT . "/resource/translations/{$locale}/messages.php";
        self::assertFileExists($path, "语言表缺失：{$path}");

        $table = require $path;
        self::assertIsArray($table, "{$locale}/messages.php 必须 return 数组");

        return array_keys($table);
    }

    #[Test]
    public function everyTransKeyExistsInEveryLocale(): void
    {
        $missing = [];
        foreach (self::LOCALES as $locale) {
            $keys = array_flip(self::localeKeys($locale));
            foreach (self::literalTransKeys() as $file => $list) {
                foreach ($list as $key) {
                    if ($key === '') {
                        continue;
                    }
                    // en 刻意空表：键名本身就是英文，缺译回落即原句
                    if ($locale === 'en') {
                        continue;
                    }
                    if (!isset($keys[$key])) {
                        $missing[] = "{$locale} 缺键（{$file}）：{$key}";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "有 trans() 键没进语言表：\n" . implode("\n", $missing));
    }

    #[Test]
    public function constantTransKeysAreRegisteredAndTranslated(): void
    {
        if (self::CONSTANT_KEYS === []) {
            // 显式 skip 而不是空循环：空循环会被 PHPUnit 判 risky，等于这条钉子悄悄失效
            $this->markTestSkipped('本棵树没有 trans(self::常量) 形式的键');
        }

        foreach (self::CONSTANT_KEYS as $name => $value) {
            foreach (self::LOCALES as $locale) {
                if ($locale === 'en') {
                    continue;
                }
                $this->assertContains(
                    $value,
                    self::localeKeys($locale),
                    "常量键 {$name} 的值在 {$locale} 表里没有对应条目"
                );
            }
        }
    }

    #[Test]
    public function allLocaleTablesHaveIdenticalKeySets(): void
    {
        $zh = self::localeKeys('zh');
        sort($zh);
        foreach (self::LOCALES as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $keys = self::localeKeys($locale);
            sort($keys);
            $this->assertSame($zh, $keys, "{$locale} 的键集合与 zh（权威表）不一致");
        }
    }

    /**
     * `$this->fail(…)` / `$this->success(…)` 的 message 实参必须经过 `trans()`。
     *
     * 逐 token 判：实参是**单个字符串字面量** ⇒ 绕过；若是 `trans(…)` 调用或变量/拼接 ⇒ 放行
     * （变量/拼接在别处已被折叠成 trans 调用，扫描器不追变量，只拦"看得见的字面量"）。
     */
    #[Test]
    public function responseMessagesAlwaysGoThroughTrans(): void
    {
        $bypassed = [];
        foreach (self::phpFiles() as $file) {
            $tokens = self::tokens($file);
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== '$this') {
                    continue;
                }
                if (!is_array($tokens[$i + 1] ?? null) || $tokens[$i + 1][1] !== '->') {
                    continue;
                }
                $method = $tokens[$i + 2][1] ?? '';
                if (!in_array($method, ['fail', 'success'], true)) {
                    continue;
                }

                // 切顶层实参
                $depth = 0;
                $args = [[]];
                for ($j = $i + 3; $j < $count; $j++) {
                    $tk = $tokens[$j];
                    if ($tk === '(') {
                        $depth++;
                        if ($depth === 1) {
                            continue;
                        }
                    }
                    if ($tk === ')') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                    if ($depth === 1 && $tk === ',') {
                        $args[] = [];
                        continue;
                    }
                    if ($depth >= 1) {
                        $args[count($args) - 1][] = $tk;
                    }
                }

                // fail(msg, code) 的消息在第 1 位；success(data, msg, code) 在第 2 位
                $arg = $args[$method === 'fail' ? 0 : 1] ?? [];
                if (count($arg) !== 1) {
                    continue;    // 无该实参，或不是"单个字面量"（调用/变量/拼接）
                }
                if (!is_array($arg[0]) || $arg[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }
                $bypassed[] = substr($file, strlen(self::ROOT) + 1) . ':' . $arg[0][2] . '  ' . $arg[0][1];
            }
        }

        $this->assertSame([], $bypassed, "响应文案没走 trans()：\n" . implode("\n", $bypassed));
    }
}
