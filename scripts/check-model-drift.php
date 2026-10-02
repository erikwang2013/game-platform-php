<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 双份文件漂移守卫（原「模型漂移守卫」）：比较 admin 与 service 两棵树里
 * **同名同路径**的文件，按**代码**（token_get_all 剥掉注释与空白后取 sha256）
 * 相等即一致、不同即漂移，输出清单。
 *
 * ⚠ 为什么按代码而不是逐字节：两棵树各自写自己的调用点说明（本仓 docblock 密度很高），
 * 措辞差异不是可执行的漂移；**代码**差异则一律算。剥注释必须走 token_get_all，
 * 正则会把字符串里的 `//`（URL）当注释吃掉。仅注释/空白不同的文件会打印 [NOTE]，不算漂移。
 *
 * ⚠ 2026-10-02 换面：旧版扫 admin/app/model 与 service/app/model，而模型本体已全部迁到
 * packages/platform-common/src/model（52 个）⇒ 两树交集为 0，实跑恒为
 * 「共 0 个同名模型 / 无漂移，全部一致」——**一门永远说没事的门比没有门更糟**。
 * 真双份面 = 下面 $targets 那四处（admin|service 的 app/functions.php、
 * app/activity/、app/middleware/、app/service/）。
 * ⚠ 其余同名面（app/api/v1/controller、app/process、app/provider；app/event 已被删）
 * 本批**未纳入**：它们是两棵树各按自己的运行角色写的（端点集合 / 进程配置 / 支付 provider
 * 本来就不同），逐条复核过再决定收不收 —— **别顺手全加进 $targets**：加进来只会把
 * $pinned 撑成一长串摆设，门照样说不出话。
 *
 * 判据：
 *   - 某树独有的文件（另一树没有）**不算漂移** —— 两树中间件/服务的集合本来就不同；
 *   - 仅注释/空白不同 ⇒ [NOTE]，不算漂移；
 *   - 代码不同且不在 $pinned 里 ⇒ DRIFT，--strict 下 exit 1；
 *   - 在 $pinned 里且两侧 sha256 与条目一致 ⇒ 放行（pin 的意义就是「哪一侧的**代码**再动一下都会变红」）；
 *   - 在 $pinned 里但 sha256 对不上 ⇒ 代码又被改过 ⇒ DRIFT（此时要么收敛、要么复核后更新该条 sha）；
 *   - $pinned 的两侧已收敛（代码相同）⇒ 只提示待清理，不算漂移（收敛是好事，不该罚）；
 *   - **同名双份文件数为 0**（目录被搬走 / 脚本改坏）⇒ --strict 下**同样 exit 1**，
 *     否则本门退回「永远说没事」。
 *
 * 用法: php scripts/check-model-drift.php [--strict] [--verbose]
 */

declare(strict_types=1);

$strict = in_array('--strict', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$root = dirname(__DIR__);
$trees = ['admin' => "$root/admin", 'service' => "$root/service"];

/** 扫描范围：相对各树 `app/` 的路径；目录取其中 *.php */
$targets = [
    'app/functions.php',
    'app/activity',
    'app/middleware',
    'app/service',
];

/**
 * 已知差异白名单：相对 app/ 的路径 => [理由, admin 正规化 sha256, service 正规化 sha256]
 *
 * 只放**复核过、暂不该由本门拦**的差异；理由必须写清「为什么是故意的」或「为什么还没收敛」。
 * sha256（剥注释后的代码）是钉子：任何一侧的**代码**再动一下，条目失效、立刻变红。
 * ⚠ 想加条目 = 先复核那两段代码，别为了把门刷绿照抄 hash。
 * ⚠ 被 pin 的文件在任一侧改了代码后本门会红：要么收敛两侧、要么复核后更新该条 sha，
 *    **不要**删条目来消音。
 */
$pinned = [
    'app/functions.php' => [
        '故意：service 侧 __() 委托 DB 版 common\service\TranslationService（C 端有调用点）；'
        . 'admin 侧没有任何 DB 翻译调用点（其 LanguageMiddleware 也不注入 TranslationService::setLocale），保留空壳。',
        '00aba3928cd622ad67c0f81ecd5aa8699e07f8f4908c045fe34f6a2dc0067b37',
        'afae0534bc79aa5dbd63ed0a1dbc878f78b99916c94311129d2987814fe40558',
    ],
    'app/middleware/LanguageMiddleware.php' => [
        '故意：service 侧额外把 locale 注入 TranslationService::setLocale()（DB 版翻译，C 端有调用点）；'
        . 'admin 无该调用点，检测顺序与 Locale::normalize() 归一两侧同构（admin 版 docblock 也自述「与 C 端同构」）。',
        'd92c31a08efc751b1db7021a72c1e0d4c039d37c529b8965ddd239b96e2a0d39',
        '8d458696bcd82c9ae842e3253285914c8d353e975cfbc35bfa6ba0a6054df7c3',
    ],
    'app/middleware/RateLimit.php' => [
        '故意：service 的敏感路由表多三条 **C 端独有**路由（/api/v1/auth/oauth、/api/v1/2fa/verify、'
        . '/api/v1/payment/callback），admin 路由表里没有这三条；默认限额与 Lua 滑动窗口实现两侧一致。',
        '8a5e08eb901b71e0ba20d3f02d9d0682b3b7e0b599efa3dff3090ca18ebd673a',
        '7d3a115b1b0c529583c98038f37b8d88710990d0108623d681653fe4d2a5d799',
    ],
];

/**
 * 正规化：token 化后丢掉注释与空白 —— 只比**代码**。
 * 不用正则剥注释：会把字符串里的 `//`（URL、CSP 里的 http://）一起吃掉。
 */
function normalizedSource(string $file): string
{
    $out = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT || $token[0] === T_WHITESPACE) {
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }

    return $out;
}

// 收集同路径双份文件
$pairs = [];
foreach ($targets as $rel) {
    $a = "{$trees['admin']}/$rel";
    $s = "{$trees['service']}/$rel";
    if (is_file($a)) {
        if (is_file($s)) {
            $pairs[$rel] = [$a, $s];
        }
        continue;
    }
    foreach (glob("$a/*.php") ?: [] as $af) {
        $name = basename($af);
        if (is_file("$s/$name")) {
            $pairs["$rel/$name"] = [$af, "$s/$name"];
        }
    }
}
ksort($pairs);

$total = count($pairs);
$drifts = [];       // path => [shaAdmin, shaService, 备注]
$pinHits = [];      // 本轮实际生效的 pin（连同理由，始终打印）
$pinStale = [];     // 两侧已一致、条目该删

foreach ($pairs as $rel => [$af, $sf]) {
    $ha = hash('sha256', normalizedSource($af));
    $hs = hash('sha256', normalizedSource($sf));
    if ($ha === $hs) {
        if (isset($pinned[$rel])) {
            $pinStale[] = $rel;                                    // 已收敛，条目该删
        } elseif (hash_file('sha256', $af) === hash_file('sha256', $sf)) {
            if ($verbose) {
                echo "[OK]   $rel\n";
            }
        } else {
            echo "[NOTE] $rel —— 仅注释/空白差异，不计漂移\n";
        }
        continue;
    }
    if (isset($pinned[$rel]) && $pinned[$rel][1] === $ha && $pinned[$rel][2] === $hs) {
        $pinHits[$rel] = $pinned[$rel][0];
        continue;
    }
    $drifts[$rel] = [$ha, $hs, isset($pinned[$rel]) ? '（白名单条目已失效：代码又被改过）' : ''];
}

echo "== 双份文件漂移清单（admin vs service，共扫 $total 个同名同路径文件）==\n";
echo '扫描范围: ' . implode('、', $targets) . "（按代码比较：剥注释与空白）\n";

if ($pinHits !== []) {
    echo "\n-- 已复核放行（\$pinned 白名单，任一侧改动即失效）--\n";
    foreach ($pinHits as $rel => $reason) {
        echo "PIN  $rel\n     理由: $reason\n";
    }
}

if ($pinStale !== []) {
    echo "\n-- 白名单条目待清理（两侧已收敛，请从 \$pinned 删除该条）--\n";
    foreach ($pinStale as $rel) {
        echo "STALE $rel\n";
    }
}

if ($total === 0) {
    echo "\n⚠ 扫描面为空：0 个双份文件 —— 目录被搬走、脚本被改坏，或两树真的不再有同名文件。\n";
    echo "  --strict 下本情形 exit 1（一门永远说没事的门比没有门更糟）；请复核 \$targets。\n";
}

if ($drifts === []) {
    if ($total > 0) {
        echo "\n无漂移，全部一致（放行 " . count($pinHits) . " 条已复核差异）。\n";
    }
} else {
    foreach ($drifts as $rel => [$ha, $hs, $note]) {
        echo "\nDRIFT: $rel $note\n";
        echo "    admin   代码 sha256=$ha\n";
        echo "    service 代码 sha256=$hs\n";
    }
    echo "\n共 " . count($drifts) . " 个双份文件漂移。若确认是故意差异：复核后加进 \$pinned 并写明理由；\n";
    echo "不是故意的 ⇒ 收敛两侧内容（别再让副本烂在原地）。\n";
}

exit($strict && ($drifts !== [] || $total === 0) ? 1 : 0);
