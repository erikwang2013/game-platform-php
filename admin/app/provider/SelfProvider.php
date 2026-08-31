<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use common\model\UserGameWallet;
use support\Db;

class SelfProvider extends GameProvider
{
    public function getBalance(int $userId, int $gameId, int $currencyId): string
    {
        $wallet = UserGameWallet::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('currency_id', $currencyId)
            ->first();
        return $wallet ? $wallet->balance : '0.00000000';
    }

    public function bet(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        return Db::transaction(function () use ($userId, $gameId, $currencyId, $amount, $roundId) {
            $wallet = UserGameWallet::where('user_id', $userId)
                ->where('game_id', $gameId)
                ->where('currency_id', $currencyId)
                ->lockForUpdate()
                ->first();

            if (!$wallet || bccomp($wallet->balance, $amount, 8) < 0) {
                $bal = $wallet ? $wallet->balance : '0';
                return ['success' => false, 'transaction_id' => '', 'balance_after' => $bal, 'error' => 'Insufficient balance'];
            }

            $before = $wallet->balance;
            $after = bcsub($before, $amount, 8);
            $wallet->balance = $after;
            $wallet->save();

            return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => $after];
        });
    }

    public function settle(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        return Db::transaction(function () use ($userId, $gameId, $currencyId, $amount, $roundId) {
            $wallet = UserGameWallet::where('user_id', $userId)
                ->where('game_id', $gameId)
                ->where('currency_id', $currencyId)
                ->lockForUpdate()
                ->first();

            $before = $wallet ? $wallet->balance : '0';
            $after = bcadd($before, $amount, 8);

            if ($wallet) {
                $wallet->balance = $after;
                $wallet->save();
            }

            return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => $after, 'win_amount' => $amount];
        });
    }

    public function refund(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, string $reason): array
    {
        return $this->settle($userId, $gameId, $currencyId, $sessionId, $amount, $roundId, ['reason' => $reason]);
    }

    public function rollback(int $userId, int $gameId, int $currencyId, string $sessionId, string $roundId): array
    {
        return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => $this->getBalance($userId, $gameId, $currencyId)];
    }

    public function verifySignature(array $payload, string $signature): bool
    {
        return true;
    }
}
