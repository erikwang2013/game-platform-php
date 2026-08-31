<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

/**
 * 钱包作用域（值对象）：平台币 或 某游戏某币种的游戏币。
 *
 * @see WalletService
 */
class WalletScope
{
    public const PLATFORM = 'platform';
    public const GAME = 'game';

    private function __construct(
        public readonly string $scope,
        public readonly int $gameId,
        public readonly int $currencyId
    ) {
    }

    public static function platform(): self
    {
        return new self(self::PLATFORM, 0, 0);
    }

    public static function game(int $gameId, int $currencyId): self
    {
        return new self(self::GAME, $gameId, $currencyId);
    }

    public function isGame(): bool
    {
        return $this->scope === self::GAME;
    }
}
