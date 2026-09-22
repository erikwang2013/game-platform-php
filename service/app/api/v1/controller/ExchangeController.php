<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\ExchangeRecord;
use common\model\Game;
use common\model\GameCurrency;
use common\model\UserGameWallet;
use common\model\UserWallet;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Log;
use support\Request;
use support\Response;
use support\Db;
use app\event\EventBus;
use common\service\NotificationService;
use common\service\VipService;

#[Apidoc\Title("兑换管理")]
#[Apidoc\Group("exchange")]
class ExchangeController extends BaseController
{
    #[Apidoc\Title("兑换询价")]
    #[Apidoc\Url("/api/v1/exchange/quote")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "game_id", type: "string", require: true, desc: "游戏ID")]
    #[Apidoc\Param(name: "currency_id", type: "string", require: true, desc: "币种ID")]
    #[Apidoc\Param(name: "direction", type: "string", require: true, desc: "方向(in/out)")]
    #[Apidoc\Param(name: "platform_amount", type: "float", require: true, desc: "平台币数量")]
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

        // Apply VIP exchange discount
        $vipDiscount = VipService::getExchangeDiscount($request->userId);
        $spreadPct = bcsub($spreadPct, bcmul($spreadPct, $vipDiscount, 8), 8);
        if (bccomp($spreadPct, '0', 8) < 0) $spreadPct = '0';

        // Apply VIP rate bonus
        $effectiveRate = $this->effectiveRate($gameCurrency, $request->userId);
        $rateError     = self::rateError($effectiveRate);
        if ($rateError !== null) {
            return $this->fail($rateError, 422);
        }

        $legs = self::exchangeLegs($direction, $platformAmount, $effectiveRate, $spreadPct);

        if ($direction === 'in') {
            // Buy: platform -> game
            return $this->success([
                'platform_amount'      => $legs['platform_amount'],
                'game_amount'          => $legs['game_gross'],
                'spread_fee'           => $legs['spread_fee'],
                'actual_game_amount'   => $legs['game_amount'],
                'rate'                 => $rate,
                'spread_pct'           => $spreadPct,
            ]);
        }

        // 'out' — Sell: game -> platform
        return $this->success([
            'platform_amount'        => $platformAmount,
            'platform_equivalent'    => $legs['platform_gross'],
            'spread_fee'             => $legs['spread_fee'],
            'actual_platform_amount' => $legs['platform_amount'],
            'rate'                   => $rate,
            'spread_pct'             => $spreadPct,
        ]);
    }

    #[Apidoc\Title("买入游戏币")]
    #[Apidoc\Url("/api/v1/exchange/buy")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "game_id", type: "string", require: true, desc: "游戏ID")]
    #[Apidoc\Param(name: "currency_id", type: "string", require: true, desc: "币种ID")]
    #[Apidoc\Param(name: "platform_amount", type: "float", require: true, desc: "平台币数量")]
    public function buy(Request $request): Response
    {
        return $this->doExchange($request, 'in');
    }

    #[Apidoc\Title("卖出游戏币")]
    #[Apidoc\Url("/api/v1/exchange/sell")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "game_id", type: "string", require: true, desc: "游戏ID")]
    #[Apidoc\Param(name: "currency_id", type: "string", require: true, desc: "币种ID")]
    #[Apidoc\Param(name: "platform_amount", type: "float", require: true, desc: "平台币数量")]
    public function sell(Request $request): Response
    {
        return $this->doExchange($request, 'out');
    }

    #[Apidoc\Title("兑换记录")]
    #[Apidoc\Url("/api/v1/exchange/records")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
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

        // Apply VIP exchange discount
        $vipDiscount = VipService::getExchangeDiscount($request->userId);
        $spreadPct = bcsub($spreadPct, bcmul($spreadPct, $vipDiscount, 8), 8);
        if (bccomp($spreadPct, '0', 8) < 0) $spreadPct = '0';

        // VIP 加成后的有效汇率 — 与 quote() 共用同一公式，避免漂移
        $effectiveRate = $this->effectiveRate($gameCurrency, $userId);
        $rateError     = self::rateError($effectiveRate);
        if ($rateError !== null) {
            return $this->fail($rateError, 422);
        }

        // 两侧发生额 — 与 quote() 同一实现，避免公式漂移
        $legs = self::exchangeLegs($direction, $platformAmount, $effectiveRate, $spreadPct);

        // Use a database transaction to ensure atomicity
        Db::beginTransaction();

        try {
            if ($direction === 'in') {
                // Deduct platform balance
                $deducted = UserWallet::deductBalance($userId, $legs['platform_amount'], 'exchange_out');
                if (!$deducted) {
                    Db::rollBack();
                    return $this->fail('Insufficient platform balance', 400);
                }

                // Add game balance
                $this->addGameBalance($userId, $gameId, $currencyId, $legs['game_amount']);
            } else {
                // Deduct game balance
                $deducted = $this->deductGameBalance($userId, $gameId, $currencyId, $legs['game_amount']);
                if (!$deducted) {
                    Db::rollBack();
                    return $this->fail('Insufficient game balance', 400);
                }

                // Add platform balance (扣费后净值)
                $added = UserWallet::addBalance($userId, $legs['platform_amount'], 'exchange_in');
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
            $record->platform_amount = $legs['platform_amount'];
            $record->game_amount     = $legs['game_amount'];
            $record->rate            = $rate;
            $record->spread_fee      = $legs['spread_fee'];
            $record->save();

            // Get wallet balance after exchange（平台侧流水已由 WalletService 写入）
            $wallet = UserWallet::where('user_id', $userId)->first();
            $balanceAfter = $wallet ? $wallet->balance : '0.0000';

            Db::commit();

            EventBus::emit('exchange.completed', ['user_id' => $userId, 'game_id' => $gameId, 'direction' => $direction, 'platform_amount' => $legs['platform_amount']]);

            NotificationService::send($userId, 'exchange', 'Exchange Completed', "Exchange {$direction}: {$legs['game_amount']} game tokens / {$legs['platform_amount']} platform tokens (game #{$gameId})", 'exchange_record', $record->id);

            return $this->success([
                'exchange_id'      => $this->encodeId($record->id),
                'direction'        => $direction,
                'platform_amount'  => $legs['platform_amount'],
                'game_amount'      => $legs['game_amount'],
                'spread_fee'       => $legs['spread_fee'],
                'rate'             => $rate,
                'balance_after'    => $balanceAfter,
            ], 'Exchange successful');
        } catch (\Throwable $e) {
            Db::rollBack();
            Log::error('Exchange failed', ['user_id' => $userId, 'direction' => $direction, 'error' => $e->getMessage()]);
            return $this->fail('Exchange failed, please try again later', 500);
        }
    }

    private function effectiveRate(GameCurrency $gameCurrency, int $userId): string
    {
        $rateBonus = VipService::getRateBonus($userId);
        return bcadd($gameCurrency->exchange_rate, bcmul($gameCurrency->exchange_rate, $rateBonus, 8), 8);
    }

    /**
     * 有效汇率必须为正：0 会让 out 方向的 bcdiv 抛未捕获的 DivisionByZeroError，
     * 负值不抛错但会算出负金额。quote 与 doExchange 共用，保证两个入口同一失败信封。
     * 返回错误消息，汇率合法时返回 null。
     */
    private static function rateError(string $effectiveRate): ?string
    {
        return bccomp($effectiveRate, '0', 8) <= 0 ? 'Exchange rate is invalid' : null;
    }

    /**
     * 兑换两侧的实际发生额（quote 与 doExchange 共用，保证报价与成交同公式）。
     *
     * $amount 是请求字段 platform_amount 承载的数量，其含义随方向而变：
     * in = 支出的平台币；out = 卖出的游戏币（字段名复用，见 docs/API.md 的 sell 一节）。
     * 因此 out 下扣减的游戏币就是 $amount 本身，【不是】折算后的平台币数。
     *
     * 返回值的游戏币/平台币金额为各自到账侧的净额，与 docs/API.md 的
     * buy/sell 响应及 game_exchange_record 的列注释（platform_amount=平台币数量、
     * game_amount=游戏币数量）一致；后台「销毁游戏币」统计取的就是 out 的 game_amount。
     *
     * @return array{game_amount:string, platform_amount:string, game_gross:string, platform_gross:string, spread_fee:string}
     */
    private static function exchangeLegs(string $direction, string $amount, string $effectiveRate, string $spreadPct): array
    {
        $pct = bcdiv($spreadPct, '100', 8);

        if ($direction === 'in') {
            $gameGross = bcmul($amount, $effectiveRate, 8);
            $spreadFee = bcmul($gameGross, $pct, 8);

            return [
                'game_amount'     => bcsub($gameGross, $spreadFee, 8),
                'platform_amount' => $amount,
                'game_gross'      => $gameGross,
                'platform_gross'  => $amount,
                'spread_fee'      => $spreadFee,
            ];
        }

        $platformGross = bcdiv($amount, $effectiveRate, 8);
        $spreadFee     = bcmul($platformGross, $pct, 8);

        return [
            'game_amount'     => $amount,
            'platform_amount' => bcsub($platformGross, $spreadFee, 8),
            'game_gross'      => $amount,
            'platform_gross'  => $platformGross,
            'spread_fee'      => $spreadFee,
        ];
    }

    /**
     * Add balance to a user's game wallet (firstOrNew, then bcadd).
     */
    private function addGameBalance(int $userId, int $gameId, int $currencyId, string $amount): void
    {
        // 事务内行锁，避免 read-modify-write 竞态（调用方已在 doExchange 事务中）
        $wallet = UserGameWallet::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet                    = new UserGameWallet();
            $wallet->id                = $this->generateId();
            $wallet->user_id           = $userId;
            $wallet->game_id           = $gameId;
            $wallet->currency_id       = $currencyId;
            $wallet->frozen_balance    = '0.0000';
            $wallet->balance           = '0.0000';
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
            ->lockForUpdate()
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
