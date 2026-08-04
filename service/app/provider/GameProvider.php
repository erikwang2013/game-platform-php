<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use app\model\Game;

abstract class GameProvider
{
    protected Game $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    abstract public function getBalance(int $userId, int $gameId, int $currencyId): string;

    abstract public function bet(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, array $meta = []): array;

    abstract public function settle(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, array $meta = []): array;

    abstract public function refund(int $userId, int $gameId, string $sessionId, string $amount, string $roundId, string $reason): array;

    abstract public function rollback(int $userId, int $gameId, string $sessionId, string $roundId): array;

    abstract public function verifySignature(array $payload, string $signature): bool;

    public function signRequest(string $method, string $path, array $body, ?int $timestamp = null): array
    {
        $ts = $timestamp ?? time();
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signStr = $this->game->id . ':' . $ts . ':' . strtoupper($method) . ':' . $path . ':' . $bodyJson;
        $signature = hash_hmac('sha256', $signStr, $this->game->api_secret);

        return ['timestamp' => $ts, 'signature' => $signature, 'game_id' => $this->game->id];
    }

    protected function config(): array
    {
        if (empty($this->game->provider_config)) {
            return [];
        }
        $decoded = json_decode($this->game->provider_config, true);
        return is_array($decoded) ? $decoded : [];
    }
}
