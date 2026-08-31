<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 批次 5 漂移守卫：比较 admin/app/model 与 service/app/model 同名模型的
 * 定义（表名/fillable/casts/timestamps/incrementing/keyType），
 * 输出漂移清单。默认只报告、退出码 0；
 * 加 --strict 时存在漂移即退出码 1（CI 用，已在 .github/workflows/ci.yml 启用）。
 *
 * 用法: php scripts/check-model-drift.php [--strict] [--verbose]
 */

declare(strict_types=1);

$strict = in_array('--strict', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$root = dirname(__DIR__);
$adminDir = "$root/admin/app/model";
$serviceDir = "$root/service/app/model";

/** 提取模型定义属性：表名、fillable、casts、时间戳等 */
function extractDefinition(string $file): array
{
    $src = file_get_contents($file);
    $def = [];

    // 表名（裸表名，前缀由宿主 config 提供）
    if (preg_match("/protected\s+\$table\s*=\s*\x27([^\x27]+)\x27/", $src, $m)) {
        $def['table'] = $m[1];
    }
    // 数组属性（fillable/casts/guarded/hidden/with）：归一化空白后整块比较
    foreach (['fillable', 'casts', 'guarded', 'hidden', 'with'] as $prop) {
        if (preg_match("/protected\s+\$$prop\s*=\s*\[(.*?)\];/s", $src, $m)) {
            $def[$prop] = preg_replace('/\s+/', '', $m[1]);
        }
    }
    foreach (['incrementing', 'timestamps'] as $prop) {
        if (preg_match("/public\s+\$$prop\s*=\s*(true|false);/", $src, $m)) {
            $def[$prop] = $m[1];
        }
    }
    if (preg_match("/protected\s+\$keyType\s*=\s*\x27([^\x27]+)\x27/", $src, $m)) {
        $def['keyType'] = $m[1];
    }
    ksort($def);
    return $def;
}

function cmpFile(string $a, string $b): bool
{
    return file_get_contents($a) === file_get_contents($b);
}

$drifts = [];
$total = 0;

foreach (glob("$adminDir/*.php") as $adminFile) {
    $name = basename($adminFile);
    $serviceFile = "$serviceDir/$name";
    if (!is_file($serviceFile)) {
        continue; // 单端存在或已迁移，非漂移
    }
    $total++;

    $a = extractDefinition($adminFile);
    $s = extractDefinition($serviceFile);

    $diffs = [];
    if ($a !== $s) {
        foreach (array_unique(array_merge(array_keys($a), array_keys($s))) as $k) {
            $va = $a[$k] ?? '<缺失>';
            $vs = $s[$k] ?? '<缺失>';
            if ($va !== $vs) {
                $diffs[] = "$k: admin=[$va] service=[$vs]";
            }
        }
    }
    // 定义一致但源码不同（方法/注释级差异，如 CountryConfig::fromLang）也计漂移
    if ($diffs === [] && !cmpFile($adminFile, $serviceFile)) {
        $diffs[] = '定义一致但源码不同（方法/注释级差异）';
    }

    if ($diffs === []) {
        if ($verbose) {
            echo "[OK]   $name\n";
        }
        continue;
    }
    $drifts[$name] = $diffs;
}

echo "== 模型漂移清单（admin vs service，共 $total 个同名模型）==\n";
if ($drifts === []) {
    echo "无漂移，全部一致。\n";
} else {
    foreach ($drifts as $name => $diffs) {
        echo "DRIFT: $name\n";
        foreach ($diffs as $d) {
            echo "    - $d\n";
        }
    }
    echo "\n共 " . count($drifts) . " 个模型漂移，批次 4 收敛后应保持清零（--strict 已在 CI 生效）。\n";
}

exit($strict && $drifts !== [] ? 1 : 0);
