<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * 两棵树的 app/provider/ 之间**没有**逐字节 parity 棘轮，本用例守的是另一条边界：
 * **admin 树的 provider/ 必须保持死代码**（不被 app/ 或 config/ 里任何代码引用）。
 *
 * 为什么不是照抄 WalletServiceTwoTreeParityTest 的形态 —— 这里实测过，字节级 parity 在此
 * 不是「棘轮」而是「放弃断言」：
 *   · 四个文件**全部**与 service 树分叉（GameProvider/ProviderFactory/SelfProvider/ThirdPartyProvider），
 *     不是少一处守卫式的漂移，而是整树两份；白名单写满 4/4 等于什么都没断言。
 *   · admin 这份是被剥过的死副本：service 的 SelfProvider 254 行（含幂等、账本流水、金额语法闸
 *     `isAmountSyntaxValid`），admin 的 82 行且 `verifySignature()` **恒返回 true**。
 *   · GameProvider 的差异还只是换行形态（多行数组 vs 单行）—— php_strip_whitespace 对行宽敏感，
 *     拿它当 parity 判据会把「重排格式」报成「语义分叉」。
 *
 * 真正的风险不是漂移（死代码漂移无害），而是**有人把它接上生产调用**：
 * 接上即等于 `verifySignature()` 一律放行。所以这里钉「仍然是死的」。
 * 若本用例变红，正确动作是二选一，**不是**往白名单里加文件：
 *   ① 改调 service 树实现；或
 *   ② 先按 service 树把本目录对齐（至少补上签名校验与金额语法闸）再把本用例改写成真 parity。
 *
 * 另：`app/admin/v1/controller/RiskUserController.php:196` 的注释仍在引用
 * `SelfProvider::isAmountSyntaxValid` 作为量词判据的理由，而该方法**只存在于 service 树**
 * —— 本目录的 SelfProvider 没有它。那条注释的归属不在本文件，改它需要其 owner 配合。
 *
 * 注意：本用例只读文件系统，不连库。
 */
class ProviderTreeStaysDeadTest extends TestCase
{
    private const PROVIDER_DIR = __DIR__ . '/../app/provider';

    #[Test]
    public function adminProviderTreeIsNotReferencedAnywhereElse(): void
    {
        $this->assertDirectoryExists(self::PROVIDER_DIR, '本用例的前提：admin 树仍保留 app/provider/');

        $hits = [];
        foreach ([dirname(__DIR__) . '/app', dirname(__DIR__) . '/config'] as $root) {
            foreach ($this->phpFiles($root) as $path) {
                if (str_starts_with($path, self::PROVIDER_DIR . '/')) {
                    continue; // 树内部互相引用是预期内的（ProviderFactory → 三个实现）
                }
                // 先剥注释再找：注释里提到 app\provider\ 不算调用点
                if (str_contains(php_strip_whitespace($path), 'app\\provider\\')) {
                    $hits[] = substr($path, strlen(dirname(__DIR__)) + 1);
                }
            }
        }

        $this->assertSame([], $hits, "admin 树的 provider/ 是被剥过的死副本（verifySignature() 恒 true），"
            . "不允许被生产代码引用：\n" . implode("\n", $hits)
            . "\n若确实要用，请改调 service 树实现，或先按 service 树对齐后再改写本用例的判据。");
    }

    /** @return string[] 绝对路径 */
    private function phpFiles(string $root): array
    {
        $out = [];
        if (!is_dir($root)) {
            return $out;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }
}
