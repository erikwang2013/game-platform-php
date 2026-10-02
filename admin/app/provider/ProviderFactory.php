<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use common\model\Game;

/**
 * ⚠⚠ 最重的一条先说：本目录的 `SelfProvider::verifySignature()` **恒返回 `true`**。
 * 谁把这棵树接上生产调用，谁就等于**关掉签名校验** —— 这比「零调用点」严重一个量级。
 *
 * 所以：admin 树**不消费**本目录（本工厂只被自己引用，外部零调用点；护栏见
 * tests/ProviderTreeStaysDeadTest.php）。真实资金路径在 service 树 `app/provider/**`
 * （含幂等、账本流水与金额语法闸），本目录是被剥过的死副本 —— 别把这里的实现当现行语义读。
 *
 * 四个文件**全部**与 service 树分叉，且不是同一类分叉，别一律当语义分叉看：
 *   · `SelfProvider` 82 行 vs service 254 行（缺幂等/账本/金额语法闸）、
 *   · `ProviderFactory` 少认 `'embedded'`（service 认 `'self', 'embedded'`）、
 *   · `GameProvider` **只是换行形态**（多行数组 vs 单行）—— 纯格式，**不是**语义分叉。
 * 正因如此本目录**不设**字节级 parity 棘轮（`php_strip_whitespace` 对行宽敏感，
 * 4/4 白名单会写满＝放弃断言），守的是「保持死代码」这条边界。
 *
 * 保留而非删除，只因 `app/admin/v1/controller/RiskUserController.php:196` 的注释仍在引用
 * `SelfProvider::isAmountSyntaxValid` 作为判据的理由，而该方法**只存在于 service 树**
 * （本目录的 SelfProvider 没有它，所以那条注释现在指向不存在的方法）。
 *
 * 若确实要用：先按 service 树对齐（至少补上签名校验与金额语法闸），或改调 service 侧实现。
 */
class ProviderFactory
{
    public static function create(Game $game): GameProvider
    {
        return match ($game->type) {
            'self' => new SelfProvider($game),
            'third_party' => new ThirdPartyProvider($game),
            default => throw new \InvalidArgumentException("Unknown game type: {$game->type}"),
        };
    }

    public static function createById(int $gameId): GameProvider
    {
        $game = Game::find($gameId);
        if (!$game) {
            throw new \InvalidArgumentException("Game not found: {$gameId}");
        }
        return self::create($game);
    }
}
