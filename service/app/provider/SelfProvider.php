<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\provider;

use common\model\GamePlayLog;
use common\model\UserGameWallet;
use app\service\WalletScope;
use app\service\WalletService;
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
        // M1: 统一钱包入口（游戏币作用域），余额不足返回 false，无残留写入
        $ok = WalletService::mutate(
            $userId,
            WalletScope::game($gameId, $currencyId),
            '-' . $amount,
            'game_spend',
            'game_round',
            0,
            'bet ' . $roundId
        );

        if (!$ok) {
            return ['success' => false, 'transaction_id' => '', 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'error' => 'Insufficient balance'];
        }

        return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId))];
    }

    public function settle(int $userId, int $gameId, int $currencyId, string $sessionId, string $amount, string $roundId, array $meta = []): array
    {
        return Db::transaction(function () use ($userId, $gameId, $currencyId, $amount, $roundId) {
            // 幂等：同一 round 只能结算/退款一次。GamePlayLog（ProviderController 在同一外层事务中插入）
            // 在提交后对重放可见，重放直接跳过入账
            if ($roundId !== '' && GamePlayLog::where('user_id', $userId)
                    ->where('game_id', $gameId)
                    ->where('round_id', $roundId)
                    ->whereIn('action', ['settle', 'refund'])
                    ->exists()) {
                return ['success' => true, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'win_amount' => '0', 'already_processed' => true];
            }

            // M1: 统一钱包入口（游戏币作用域）。结算为信用入账，账户缺失时隐式建户。
            $ok = WalletService::mutate(
                $userId,
                WalletScope::game($gameId, $currencyId),
                '+' . $amount,
                'game_earn',
                'game_round',
                0,
                'settle ' . $roundId
            );

            return ['success' => $ok, 'transaction_id' => $roundId, 'balance_after' => WalletService::balance($userId, WalletScope::game($gameId, $currencyId)), 'win_amount' => $amount];
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
