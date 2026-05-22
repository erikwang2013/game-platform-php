<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\ExchangeRecord;
use common\model\Game;
use common\model\GameCurrency;
use common\model\Transaction;
use common\model\UserGameWallet;
use common\model\UserWallet;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;
use support\Db;

/**
 * @Apidoc\Title("兑换管理")
 * @Apidoc\Group("exchange")
 */
class ExchangeController extends BaseController
{
    /**
     * @Apidoc\Title("兑换询价")
     * @Apidoc\Url("/api/exchange/quote")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏ID")
     * @Apidoc\Param(name="currency_id", type="string", require=true, desc="币种ID")
     * @Apidoc\Param(name="direction", type="string", require=true, desc="方向(in/out)")
     * @Apidoc\Param(name="platform_amount", type="float", require=true, desc="平台币数量")
     */
    public function quote(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id'         => 'required',
            'currency_id'     => 'required',
            'direction'       => 'required|in:in,out',
            'platform_amount' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId         = $this->decodeId($request->input('game_id'));
        $currencyId     = $this->decodeId($request->input('currency_id'));
        $direction      = $request->input('direction');
        $platformAmount = (string) $request->input('platform_amount');

        // Find game currency
        $gameCurrency = GameCurrency::where('id', $currencyId)
            ->where('game_id', $gameId)
            ->first();

        if (!$gameCurrency) {
            return $this->fail('Game currency not found', 404);
        }

        $rate      = $gameCurrency->exchange_rate;
        $spreadPct = $gameCurrency->spread_pct;

        if ($direction === 'in') {
            // Buy: platform -> game
            $gameAmount      = bcmul($platformAmount, $rate, 8);
            $spreadFee       = bcmul($gameAmount, bcdiv($spreadPct, '100', 8), 8);
            $actualGameAmount = bcsub($gameAmount, $spreadFee, 8);

            return $this->success([
                'platform_amount'      => $platformAmount,
                'game_amount'          => $gameAmount,
                'spread_fee'           => $spreadFee,
                'actual_game_amount'   => $actualGameAmount,
                'rate'                 => $rate,
                'spread_pct'           => $spreadPct,
            ]);
        }

        // 'out' — Sell: game -> platform
        $platformEquivalent   = bcdiv($platformAmount, $rate, 8);
        $spreadFee            = bcmul($platformEquivalent, bcdiv($spreadPct, '100', 8), 8);
        $actualPlatformAmount = bcsub($platformEquivalent, $spreadFee, 8);

        return $this->success([
            'platform_amount'        => $platformAmount,
            'platform_equivalent'    => $platformEquivalent,
            'spread_fee'             => $spreadFee,
            'actual_platform_amount' => $actualPlatformAmount,
            'rate'                   => $rate,
            'spread_pct'             => $spreadPct,
        ]);
    }

    /**
     * @Apidoc\Title("买入游戏币")
     * @Apidoc\Url("/api/exchange/buy")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏ID")
     * @Apidoc\Param(name="currency_id", type="string", require=true, desc="币种ID")
     * @Apidoc\Param(name="platform_amount", type="float", require=true, desc="平台币数量")
     */
    public function buy(Request $request): Response
    {
        return $this->doExchange($request, 'in');
    }

    /**
     * @Apidoc\Title("卖出游戏币")
     * @Apidoc\Url("/api/exchange/sell")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏ID")
     * @Apidoc\Param(name="currency_id", type="string", require=true, desc="币种ID")
     * @Apidoc\Param(name="platform_amount", type="float", require=true, desc="平台币数量")
     */
    public function sell(Request $request): Response
    {
        return $this->doExchange($request, 'out');
    }

    /**
     * @Apidoc\Title("兑换记录")
     * @Apidoc\Url("/api/exchange/records")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function records(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $paginator = ExchangeRecord::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $record) {
            $items[] = [
                'id'              => $this->encodeId($record->id),
                'game_id'         => $this->encodeId($record->game_id),
                'currency_id'     => $this->encodeId($record->currency_id),
                'direction'       => $record->direction,
                'platform_amount' => $record->platform_amount,
                'game_amount'     => $record->game_amount,
                'rate'            => $record->rate,
                'spread_fee'      => $record->spread_fee,
                'created_at'      => $record->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * Execute exchange transaction (buy or sell).
     */
    private function doExchange(Request $request, string $direction): Response
    {
        $validator = validator($request->all(), [
            'game_id'         => 'required',
            'currency_id'     => 'required',
            'platform_amount' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId         = $request->userId;
        $gameId         = $this->decodeId($request->input('game_id'));
        $currencyId     = $this->decodeId($request->input('currency_id'));
        $platformAmount = (string) $request->input('platform_amount');

        // Find game currency and verify game is active
        $gameCurrency = GameCurrency::where('id', $currencyId)
            ->where('game_id', $gameId)
            ->first();

        if (!$gameCurrency) {
            return $this->fail('Game currency not found', 404);
        }

        $game = Game::find($gameId);
        if (!$game || (int) $game->status !== 1) {
            return $this->fail('Game is not available', 403);
        }

        $rate      = $gameCurrency->exchange_rate;
        $spreadPct = $gameCurrency->spread_pct;

        // Calculate amounts
        if ($direction === 'in') {
            // Buy: spend platform tokens to get game tokens
            $gameAmount = bcmul($platformAmount, $rate, 8);
            $spreadFee  = bcmul($gameAmount, bcdiv($spreadPct, '100', 8), 8);
        } else {
            // Sell: spend game tokens to get platform tokens
            $gameAmount = bcmul($platformAmount, $rate, 8);
            $spreadFee  = bcmul($gameAmount, bcdiv($spreadPct, '100', 8), 8);
        }

        // Use a database transaction to ensure atomicity
        Db::beginTransaction();

        try {
            if ($direction === 'in') {
                // Deduct platform balance
                $deducted = UserWallet::deductBalance($userId, $platformAmount);
                if (!$deducted) {
                    Db::rollBack();
                    return $this->fail('Insufficient platform balance', 400);
                }

                // Add game balance
                $this->addGameBalance($userId, $gameId, $currencyId, $gameAmount);
            } else {
                // Deduct game balance
                $deducted = $this->deductGameBalance($userId, $gameId, $currencyId, $gameAmount);
                if (!$deducted) {
                    Db::rollBack();
                    return $this->fail('Insufficient game balance', 400);
                }

                // Add platform balance
                $added = UserWallet::addBalance($userId, $gameAmount);
                if (!$added) {
                    Db::rollBack();
                    return $this->fail('Failed to add platform balance', 500);
                }
            }

            // Create exchange record
            $record = new ExchangeRecord();
            $record->id              = $this->generateId();
            $record->user_id         = $userId;
            $record->game_id         = $gameId;
            $record->currency_id     = $currencyId;
            $record->direction       = $direction;
            $record->platform_amount = $platformAmount;
            $record->game_amount     = $gameAmount;
            $record->rate            = $rate;
            $record->spread_fee      = $spreadFee;
            $record->save();

            // Get wallet balance after exchange
            $wallet = UserWallet::where('user_id', $userId)->first();
            $balanceAfter = $wallet ? $wallet->balance : '0.0000';

            // Create transaction record
            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $userId;
            $transaction->type          = $direction === 'in' ? 'exchange_buy' : 'exchange_sell';
            $transaction->amount        = $direction === 'in' ? '-' . $platformAmount : $gameAmount;
            $transaction->balance_after = $balanceAfter;
            $transaction->ref_type      = 'exchange_record';
            $transaction->ref_id        = $record->id;
            $transaction->remark        = $direction === 'in'
                ? "Exchange buy: {$platformAmount} platform -> {$gameAmount} game (game_id: {$gameId})"
                : "Exchange sell: {$gameAmount} game -> platform (game_id: {$gameId})";
            $transaction->save();

            Db::commit();

            return $this->success([
                'exchange_id'      => $this->encodeId($record->id),
                'direction'        => $direction,
                'platform_amount'  => $platformAmount,
                'game_amount'      => $gameAmount,
                'spread_fee'       => $spreadFee,
                'rate'             => $rate,
                'balance_after'    => $balanceAfter,
            ], 'Exchange successful');
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->fail('Exchange failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add balance to a user's game wallet (firstOrNew, then bcadd).
     */
    private function addGameBalance(int $userId, int $gameId, int $currencyId, string $amount): void
    {
        $wallet = UserGameWallet::firstOrNew([
            'user_id'     => $userId,
            'game_id'     => $gameId,
            'currency_id' => $currencyId,
        ]);

        if (!$wallet->exists) {
            $wallet->id             = $this->generateId();
            $wallet->frozen_balance = '0.0000';
            $wallet->balance        = '0.0000';
        }

        $wallet->balance = bcadd($wallet->balance, $amount, 8);
        $wallet->save();
    }

    /**
     * Deduct balance from a user's game wallet. Returns true on success.
     */
    private function deductGameBalance(int $userId, int $gameId, int $currencyId, string $amount): bool
    {
        $wallet = UserGameWallet::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('currency_id', $currencyId)
            ->first();

        if (!$wallet) {
            return false;
        }

        if (bccomp($wallet->balance, $amount, 8) < 0) {
            return false;
        }

        $wallet->balance = bcsub($wallet->balance, $amount, 8);
        $wallet->save();

        return true;
    }
}
