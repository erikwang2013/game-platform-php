<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 一次性迁移脚本：hg docblock 注解 → erikwang2013 PHP 8 属性
 *
 *   @Apidoc\Param(name="page", type="int")   →   #[Apidoc\Param(name: "page", type: "int")]
 *
 * 用法:
 *   php scripts/migrate-apidoc-annotations.php --dry-run   # 只看报告，不写文件
 *   php scripts/migrate-apidoc-annotations.php             # 实际写入
 *
 * 安全约束:
 * - 只替换 docblock 中的 @Apidoc\* 行与 use 语句，不触碰任何业务代码
 * - 参数按「引号/括号感知」扫描切分，绝不 explode(',')
 * - 值原样保留（中文、全角标点、true/false、整数、数组字面量）
 * - 出现 `{...}` 数组参数或无法判定的形态 → 标记 needs-manual，不猜测、不写该文件
 * - 转换后做一次「解析等价」自检：新旧注解的 (名称, 规范化参数) 集合必须一致
 */

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

const TARGET_DIRS = [
    'service/app/api/v1/controller',
    'admin/app/admin/v1/controller',
    'admin/app/api/v1/controller',
];

/** 顶层逗号切分（引号/括号感知） */
function splitArgs(string $s): array
{
    $out = [];
    $buf = '';
    $depth = 0;
    $quote = null;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $s[++$i];
                }
                continue;
            }
            if ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
            $buf .= $c;
            continue;
        }
        if ($c === '{' || $c === '[' || $c === '(') {
            $depth++;
            $buf .= $c;
            continue;
        }
        if ($c === '}' || $c === ']' || $c === ')') {
            $depth--;
            $buf .= $c;
            continue;
        }
        if ($c === ',' && $depth === 0) {
            $out[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') {
        $out[] = $buf;
    }
    return array_map('trim', $out);
}

/** 顶层 k=v / k:v 切分；非命名参数返回 null */
function splitKeyValue(string $arg, array $seps = ['=']): ?array
{
    $depth = 0;
    $quote = null;
    $len = strlen($arg);
    for ($i = 0; $i < $len; $i++) {
        $c = $arg[$i];
        if ($quote !== null) {
            if ($c === '\\') {
                $i++;
                continue;
            }
            if ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
            continue;
        }
        if ($c === '{' || $c === '[' || $c === '(') {
            $depth++;
            continue;
        }
        if ($c === '}' || $c === ']' || $c === ')') {
            $depth--;
            continue;
        }
        if ($depth === 0 && in_array($c, $seps, true)) {
            // 排除 ==, ===, =>, >=, <=, != 等非赋值形态
            $prev = $i > 0 ? $arg[$i - 1] : '';
            $next = $i + 1 < $len ? $arg[$i + 1] : '';
            if ($next === '=' || $next === '>' || strpos('!<>', $prev) !== false) {
                return null;
            }
            return [trim(substr($arg, 0, $i)), trim(substr($arg, $i + 1))];
        }
    }
    return null;
}

/** 引号外是否存在花括号（路径占位符 {hashid} 在引号内，不算数组字面量） */
function hasUnquotedBrace(string $s): bool
{
    $quote = null;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            if ($c === '\\') {
                $i++;
                continue;
            }
            if ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
            continue;
        }
        if ($c === '{' || $c === '}') {
            return true;
        }
    }
    return false;
}

/** 抽取一行中所有 @Apidoc\X(...) */
function extractAnnotations(string $line): array
{
    $res = [];
    $offset = 0;
    $len = strlen($line);
    while (preg_match('/@Apidoc\\\\(\w+)[ \t]*/', $line, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $name = $m[1][0];
        $after = $m[0][1] + strlen($m[0][0]);
        $args = '';
        if ($after < $len && $line[$after] === '(') {
            $depth = 0;
            $quote = null;
            $i = $after;
            for (; $i < $len; $i++) {
                $c = $line[$i];
                if ($quote !== null) {
                    if ($c === '\\') {
                        $i++;
                        continue;
                    }
                    if ($c === $quote) {
                        $quote = null;
                    }
                    continue;
                }
                if ($c === '"' || $c === "'") {
                    $quote = $c;
                    continue;
                }
                if ($c === '(') {
                    $depth++;
                    continue;
                }
                if ($c === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $args = substr($line, $after + 1, $i - $after - 1);
            $offset = $i + 1;
        } else {
            $offset = $after;
        }
        $res[] = ['name' => $name, 'args' => $args];
    }
    return $res;
}

/** 规范化为可比较形态：named=value 列表（排序） */
function canonicalArgs(array $args): ?array
{
    $named = [];
    $positional = [];
    foreach ($args as $idx => $arg) {
        $kv = splitKeyValue($arg, ['=', ':']);
        if ($kv === null) {
            if (hasUnquotedBrace($arg)) {
                return null; // 数组字面量：不做等价推断
            }
            $positional[] = [$idx, $arg];
        } else {
            $named[$kv[0]] = $kv[1];
        }
    }
    if ($named === []) {
        return array_map(static fn($p) => '#' . $p[1], $positional);
    }
    if (count($positional) > 1) {
        return null;
    }
    if (count($positional) === 1) {
        $named['name'] = $positional[0][1];
    }
    ksort($named);
    $out = [];
    foreach ($named as $k => $v) {
        $out[] = $k . '=' . $v;
    }
    return $out;
}

/** 注解 → 属性行 */
function renderAttribute(string $name, string $argsRaw): ?string
{
    if (trim($argsRaw) === '') {
        return '#[Apidoc\\' . $name . ']';
    }
    $args = splitArgs($argsRaw);
    $named = [];
    $positional = [];
    foreach ($args as $idx => $arg) {
        if ($arg === '') {
            continue;
        }
        if (hasUnquotedBrace($arg)) {
            return null; // 数组字面量 → needs-manual
        }
        $kv = splitKeyValue($arg, ['=']);
        if ($kv === null) {
            if ($named !== []) {
                return null; // 位置参数出现在命名参数之后 → PHP 语法不允许 → needs-manual
            }
            $positional[$idx] = $arg;
        } else {
            $named[$kv[0]] = $kv[1];
        }
    }
    if ($named === []) {
        // 纯位置参数：原样保留（单参数时与 name: 语义不同，不能改写）
        $parts = [];
        foreach ($positional as $v) {
            $parts[] = $v;
        }
        return '#[Apidoc\\' . $name . '(' . implode(', ', $parts) . ')]';
    }
    if (count($positional) > 1) {
        return null;
    }
    $parts = [];
    if (count($positional) === 1) {
        $parts[] = 'name: ' . reset($positional);
    }
    foreach ($named as $k => $v) {
        $parts[] = $k . ': ' . $v;
    }
    return '#[Apidoc\\' . $name . '(' . implode(', ', $parts) . ')]';
}

// ---------------------------------------------------------------- 主流程

$touched = [];
$skipped = [];
$manual = [];
$lint = [];
$selfCheckFail = [];
$nameStat = [];
$stat = ['annotations' => 0, 'prose_blocks' => 0];
$files = [];

foreach (TARGET_DIRS as $dir) {
    $full = $root . '/' . $dir;
    if (!is_dir($full)) {
        continue;
    }
    foreach (glob($full . '/*.php') as $f) {
        $files[] = [$dir, $f];
    }
}
sort($files);

foreach ($files as [$dir, $file]) {
    $rel = $dir . '/' . basename($file);
    $src = file_get_contents($file);
    if ($src === false) {
        continue;
    }
    if (strpos($src, '@Apidoc') === false) {
        $skipped[] = $rel;
        continue;
    }

    $nl = strpos($src, "\r\n") !== false ? "\r\n" : "\n";
    if (!preg_match_all('#/\*\*.*?\*/#s', $src, $blocks, PREG_OFFSET_CAPTURE)) {
        $skipped[] = $rel;
        continue;
    }

    $repl = [];
    $origAnnotations = [];
    $newAnnotations = [];
    $proseBlocks = 0;
    $fileManual = [];
    $indentFail = false;

    foreach ($blocks[0] as [$block, $start]) {
        $inner = substr($block, 3, -2); // 去掉 /** 与 */
        $lines = preg_split('/\r?\n/', $inner);
        $prose = [];
        $annotations = [];
        $hasAnnotation = false;
        foreach ($lines as $line) {
            $line = preg_replace('/^[ \t]*\*?[ \t]?/', '', $line);
            $line = rtrim($line);
            if (strpos($line, '@Apidoc') !== false) {
                $hasAnnotation = true;
                foreach (extractAnnotations($line) as $a) {
                    $annotations[] = $a;
                }
                continue;
            }
            $prose[] = $line;
        }
        if (!$hasAnnotation) {
            continue;
        }
        // 去掉首尾空行
        while ($prose !== [] && trim($prose[0]) === '') {
            array_shift($prose);
        }
        while ($prose !== [] && trim($prose[count($prose) - 1]) === '') {
            array_pop($prose);
        }
        if ($prose !== []) {
            $proseBlocks++;
        }
        foreach ($annotations as $a) {
            $origAnnotations[] = [$a['name'], canonicalArgs(splitArgs($a['args']))];
        }

        // 缩进 = 含 /** 那一行的前导空白
        $lineStart = strrpos(substr($src, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $indent = '';
        if (preg_match('/^[ \t]*/', substr($src, $lineStart, $start - $lineStart), $im)) {
            $indent = $im[0];
        }

        // 首个输出行不补 $indent：匹配起点是 `/**`，其行首缩进仍留在原文里，
        // 再补一次会让首行多出一级缩进（曾导致 228 行首行 8 空格）
        $out = [];
        $firstOut = true;
        $emit = static function (string $text) use (&$out, &$firstOut, $indent): void {
            $out[] = ($firstOut ? '' : $indent) . $text;
            $firstOut = false;
        };
        if ($prose !== []) {
            $emit('/**');
            foreach ($prose as $p) {
                $emit($p === '' ? ' *' : ' * ' . $p);
            }
            $emit(' */');
        }
        foreach ($annotations as $a) {
            $attr = renderAttribute($a['name'], $a['args']);
            if ($attr === null) {
                $fileManual[] = '@Apidoc\\' . $a['name'];
                continue;
            }
            $emit($attr);
            $newAnnotations[] = [$a['name'], canonicalArgs(splitArgs($a['args']))];
            $nameStat[$a['name']] = ($nameStat[$a['name']] ?? 0) + 1;
        }
        $text = implode($nl, $out);
        // 首行必须无缩进（缩进由原文行首保留），否则说明又补了一次
        $firstLine = strstr($text, $nl, true);
        if ($firstLine !== false && ltrim($firstLine, " \t") !== $firstLine) {
            $indentFail = true;
        }
        $repl[] = [$start, strlen($block), $text];
    }

    if ($repl === []) {
        $skipped[] = $rel;
        continue;
    }
    if ($fileManual !== []) {
        $manual[$rel] = implode(', ', array_unique($fileManual));
        continue;
    }
    if ($indentFail) {
        $selfCheckFail[$rel] = 'first-line indent';
        continue;
    }

    // use 语句存在性判断必须基于 $src（替换前原文）
    $hasHgUse = strpos($src, 'use hg\\apidoc\\annotation as Apidoc;') !== false;
    if (!$hasHgUse && strpos($src, '#[Apidoc\\') === false) {
        $manual[$rel] = 'missing `use hg\\apidoc\\annotation as Apidoc;`';
        continue;
    }

    // 从后往前替换，偏移不失效
    usort($repl, static fn($a, $b) => $b[0] <=> $a[0]);
    $new = $src;
    foreach ($repl as [$start, $len, $text]) {
        $new = substr($new, 0, $start) . $text . substr($new, $start + $len);
    }

    // use 替换必须在偏移替换「之后」：erikwang2013 比 hg 长 10 字节，
    // 提前替换会让其后所有偏移串位（曾导致 68 个文件语法损坏）
    if ($hasHgUse) {
        $new = str_replace('use hg\\apidoc\\annotation as Apidoc;', 'use erikwang2013\\apidoc\\annotation as Apidoc;', $new);
    }

    // 语法自检：与 php -l 同一套 lexer，非法输出绝不落盘
    try {
        token_get_all($new, TOKEN_PARSE);
    } catch (\Throwable $e) {
        $selfCheckFail[$rel] = 'syntax: ' . $e->getMessage();
        continue;
    }

    // 解析等价自检：新旧 (名称, 规范化参数) 集合必须一致
    $check = [];
    if (preg_match_all('/#\[Apidoc\\\\(\w+)(?:\((.*?)\))?\]/', $new, $am, PREG_SET_ORDER)) {
        foreach ($am as $a) {
            $check[] = [$a[1], canonicalArgs(splitArgs($a[2] ?? ''))];
        }
    }
    $a1 = $origAnnotations;
    $a2 = $check;
    $key = static fn(array $x) => json_encode([$x[0], $x[1]], JSON_UNESCAPED_UNICODE);
    $s1 = array_map($key, $a1);
    $s2 = array_map($key, $a2);
    sort($s1);
    sort($s2);
    if ($s1 !== $s2) {
        $selfCheckFail[$rel] = 'before=' . count($s1) . ' after=' . count($s2);
        continue;
    }

    $stat['annotations'] += count($origAnnotations);
    $stat['prose_blocks'] += $proseBlocks;
    $touched[] = $rel;
    if (!$dryRun) {
        file_put_contents($file, $new);
    }
}

// ---------------------------------------------------------------- 报告
echo ($dryRun ? "[DRY-RUN] " : "[WRITE] ") . "迁移完成\n";
echo "转换文件: " . count($touched) . "\n";
echo "注解总数: {$stat['annotations']}  (含说明文字的类 docblock: {$stat['prose_blocks']})\n";
echo "跳过（无 @Apidoc）: " . count($skipped) . "\n";
foreach ($skipped as $s) {
    echo "  - $s\n";
}
echo "needs-manual: " . count($manual) . "\n";
foreach ($manual as $f => $why) {
    echo "  ! $f : $why\n";
}
echo "自检失败: " . count($selfCheckFail) . "\n";
foreach ($selfCheckFail as $f => $why) {
    echo "  ! $f : $why\n";
}
ksort($nameStat);
echo "注解分布: ";
$pairs = [];
foreach ($nameStat as $n => $c) {
    $pairs[] = "$n=$c";
}
echo implode(' ', $pairs) . "\n";

// 注解类存在性校验
$annoDir = $root . '/service/vendor/erikwang2013/apidoc-php/src/annotation';
$missing = [];
foreach (array_keys($nameStat) as $n) {
    if (!is_file($annoDir . '/' . $n . '.php')) {
        $missing[] = $n;
    }
}
echo "缺失注解类: " . (count($missing) ? implode(', ', $missing) : '无') . "\n";

exit(count($selfCheckFail) || count($manual) ? 1 : 0);
