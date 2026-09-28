<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 两棵树的 WalletService.php 必须语义同形（去掉注释与空白后逐字节相同）。
 *
 * 为什么值得一条钉子：`lock()` 全仓唯一的生产调用点在 **admin** 树
 * （app/admin/v1/controller/RiskUserController.php:138），而 service 树也有同名同实现的副本。
 * 只改一边 = 唯一真实调用路径上的记账缺陷原样活着，且两树对同一张 game_user_wallet 的口径分叉。
 * 本仓有过先例：Encryptable 的 cipher 两树分歧导致 admin 读 service 写的抛 500、
 * service 读 admin 写的静默返回密文（恒 401），且**单边改只会把两种故障对调**。
 *
 * 所以这条守的不是洁癖，是资金记账语义；差异一旦出现，必须是一次自觉的决定 + 同步两树。
 * 允许的差异只有注释（php_strip_whitespace 会去掉注释与空白，保留代码与字符串字面量）。
 *
 * 注意：本用例只读文件系统，不连库。
 */
class WalletServiceTwoTreeParityTest extends TestCase
{
    private const SERVICE = __DIR__ . '/../../service/app/service/WalletService.php';
    private const ADMIN   = __DIR__ . '/../app/service/WalletService.php';

    #[Test]
    public function bothTreesStaySemanticallyIdentical(): void
    {
        foreach ([self::SERVICE, self::ADMIN] as $path) {
            $this->assertFileExists($path, "两树 WalletService 缺一：{$path}");
        }

        $service = php_strip_whitespace(self::SERVICE);
        $admin = php_strip_whitespace(self::ADMIN);

        $this->assertSame(
            $service,
            $admin,
            "两树 WalletService 去掉注释与空白后必须逐字节相同（差异只允许是注释）。"
            . "若你确实要单边改动，先读本条 docblock：lock() 的唯一调用点在 admin 树，"
            . "单边改会让另一棵树的调用方沿用旧记账语义。"
        );
    }
}
