<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\GamePlayLog;
use app\model\UserGameWallet;
use app\model\UserWallet;
use app\model\Transaction;
use app\provider\ProviderFactory;
use support\Db;
use support\Request;
use support\Response;

/**
 * 游戏提供商回调接口
 *
 * 第三方游戏通过此 API 与平台交互（查余额、下注、结算、退款）。
 * 所有接口需 ProviderAuth 中间件验证 HMAC-SHA256 签名。
 */
class ProviderController extends BaseController
{
    /**
     * 查询用户余额
     * POST /api/provider/balance
     */
    public function balance(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $currencyId = (int) $request->input('currency_id', 0);

        if ($userId <= 0) {
            return $this->fail('user_id required', 422);
        }

        $provider = ProviderFactory::create($request->game);
        $balance = $provider->getBalance($userId, $request->gameId, $currencyId);

        return $this->success(['balance' => $balance]);
    }

    /**
     * 通知下注
     * POST /api/provider/bet
     */
    public function bet(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if ($userId <= 0 || empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $request, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->bet($userId, $request->gameId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                $this->logPlay($userId, $request->gameId, $sessionId, $roundId, 'bet', $amount, $result['balance_after'] ?? '0', $meta);
            }

            return $this->success($result);
        });
    }

    /**
     * 通知结算
     * POST /api/provider/settle
     */
    public function settle(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if ($userId <= 0 || empty($sessionId)) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $request, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->settle($userId, $request->gameId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                $winAmount = $result['win_amount'] ?? '0';
                $this->logPlay($userId, $request->gameId, $sessionId, $roundId, 'settle', $winAmount, $result['balance_after'] ?? '0', $meta);

                // Update session ended_at
                GamePlayLog::where('session_id', $sessionId)
                    ->where('action', 'start')
                    ->update(['ended_at' => date('Y-m-d H:i:s')]);
            }

            return $this->success($result);
        });
    }

    /**
     * 通知退款
     * POST /api/provider/refund
     */
    public function refund(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $reason = $request->input('reason', 'unknown');

        if ($userId <= 0 || empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $request, $sessionId, $amount, $roundId, $reason) {
            $result = $provider->refund($userId, $request->gameId, $sessionId, $amount, $roundId, $reason);

            if ($result['success']) {
                $this->logPlay($userId, $request->gameId, $sessionId, $roundId, 'refund', $amount, $result['balance_after'] ?? '0', ['reason' => $reason]);
            }

            return $this->success($result);
        });
    }

    private function logPlay(int $userId, int $gameId, string $sessionId, string $roundId, string $action, string $amount, string $balanceAfter, array $meta): void
    {
        $log = new GamePlayLog();
        $log->id = $this->generateId();
        $log->user_id = $userId;
        $log->game_id = $gameId;
        $log->session_id = $sessionId;
        $log->round_id = $roundId;
        $log->action = $action;
        $log->game_amount_before = '0';
        $log->game_amount_change = $amount;
        $log->game_amount_after = $balanceAfter;
        $log->metadata = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $log->started_at = date('Y-m-d H:i:s');
        $log->save();
    }
}
