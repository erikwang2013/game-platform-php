<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 两棵树的 app/activity/ 必须逐字节同形（去注释与空白后）。
 *
 * 为什么值得一条钉子（不是洁癖）：本目录已有过静默漂移 —— admin 的 DailyTaskHandler::canJoin
 * 比 service 少一段守卫（service 侧注释写明：不挡的话「加一个 game_id=0 的每日任务即可
 * 点一下白拿奖」，且该行随即被置 REWARDED，当天真实事件再也累加不进去）。
 * 漂移能长期存活的原因就是没有护栏：同一份策略类被复制成两份，改一边不会红。
 *
 * **影响要如实界定**（别把本用例说成在挡住一个活缺陷）：admin 树唯一的 `$handler->` 调用点是
 * `app/admin/v1/controller/ActivityController.php:185`，只调 `defaultConfig()`；
 * `canJoin`/`onProgress` 在 admin **从不被调用** ⇒ 漂移当时无运行时影响，缺的是护栏本身。
 *
 * 允许的差异只有注释（php_strip_whitespace 去掉注释与空白，保留代码与字符串字面量）。
 * 因此下面的 Hash 比对对**行宽/换行敏感**：把多行数组改成单行会让本用例变红 —— 那是刻意的，
 * 想改形态就两棵树一起改。
 *
 * 注意：本用例只读文件系统，不连库。
 */
class ActivityTwoTreeParityTest extends TestCase
{
    private const SERVICE = __DIR__ . '/../../service/app/activity';
    private const ADMIN   = __DIR__ . '/../app/activity';

    #[Test]
    public function bothTreesExposeTheSameFiles(): void
    {
        $this->assertSame(
            $this->relFiles(self::SERVICE),
            $this->relFiles(self::ADMIN),
            '两棵树 app/activity/ 的文件清单必须一致：新增策略类时两棵树都要加（或明确本用例不覆盖它）。'
        );
    }

    #[Test]
    public function bothTreesStaySemanticallyIdentical(): void
    {
        foreach ($this->relFiles(self::SERVICE) as $rel) {
            $service = self::SERVICE . '/' . $rel;
            $admin   = self::ADMIN . '/' . $rel;
            $this->assertFileExists($admin, "admin 树缺 activity 实现：{$rel}");

            $this->assertSame(
                php_strip_whitespace($service),
                php_strip_whitespace($admin),
                "两棵树 app/activity/{$rel} 去掉注释与空白后必须逐字节相同（差异只允许是注释）。"
                . '若你确实要单边改动，先读本条 docblock：这份策略类是两棵树的副本，'
                . '单边改会让另一棵树沿用旧语义，而漂移不会自己红。'
            );
        }
    }

    /** @return string[] 目录下的 php 文件名（升序），无子目录 */
    private function relFiles(string $dir): array
    {
        $out = [];
        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $out[] = basename($path);
        }
        sort($out);

        return $out;
    }
}
