<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use app\model\Game;

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
