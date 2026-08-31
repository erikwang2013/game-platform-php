<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\event\EventBus;
use app\model\GamePlayLog;
use app\provider\ProviderFactory;
use app\service\AntiCheatService;
use app\service\GamePlayRecorder;
use support\Db;
use support\Request;
use support\Response;

/**
 * 自研/内嵌游戏 SDK 接口（M5）
 *
 * 与 /api/provider/* 相同的资金语义（SelfProvider，平台持有余额），
 * 认证基于 SDK 会话令牌（SdkSessionAuth），user_id 一律取自会话。
 */
class GameSdkController extends BaseController
{
    /**
     * 查询用户游戏余额
     * POST /api/game/balance
     */
    public function balance(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $provider = ProviderFactory::create($request->game);
        return $this->success(['balance' => $provider->getBalance($request->userId, $request->gameId, $currencyId)]);
    }

    /**
     * 通知下注
     * POST /api/game/bet
     */
    public function bet(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if (empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $request, $currencyId, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->bet($request->userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                GamePlayRecorder::record($request, $request->userId, $request->gameId, $sessionId, $roundId, 'bet', $amount, $result['balance_after'] ?? '0', $meta);
            }

            return $this->success($result);
        });
    }

    /**
     * 通知结算
     * POST /api/game/settle
     */
    public function settle(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if (empty($sessionId)) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $request, $currencyId, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->settle($request->userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                $winAmount = $result['win_amount'] ?? '0';
                GamePlayRecorder::record($request, $request->userId, $request->gameId, $sessionId, $roundId, 'settle', $winAmount, $result['balance_after'] ?? '0', $meta);

                // Update session ended_at
                GamePlayLog::where('session_id', $sessionId)
                    ->where('action', 'start')
                    ->update(['ended_at' => date('Y-m-d H:i:s')]);

                // 自研游戏事件统一映射（M5）：喂 M3 活动引擎与风控
                EventBus::emit('game.round_settled', [
                    'user_id'    => $request->userId,
                    'game_id'    => $request->gameId,
                    'session_id' => $sessionId,
                    'round_id'   => $roundId,
                    'result'     => (string) ($meta['result'] ?? ''),
                    'win_amount' => $winAmount,
                ]);

                // 与 provider 回调一致的反作弊旁路（非可靠事件，异常不阻塞主链路）
                EventBus::emit(AntiCheatService::EVENT_ROUND_FINISHED, [
                    'user_id'    => $request->userId,
                    'game_id'    => $request->gameId,
                    'session_id' => $sessionId,
                    'round_id'   => $roundId,
                    'result'     => (string) ($meta['result'] ?? ''),
                    'win_amount' => $winAmount,
                ]);
            }

            return $this->success($result);
        });
    }

    /**
     * 通知退款
     * POST /api/game/refund
     */
    public function refund(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $reason = (string) $request->input('reason', 'unknown');

        if (empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $request, $currencyId, $sessionId, $amount, $roundId, $reason) {
            $result = $provider->refund($request->userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $reason);

            if ($result['success']) {
                GamePlayRecorder::record($request, $request->userId, $request->gameId, $sessionId, $roundId, 'refund', $amount, $result['balance_after'] ?? '0', ['reason' => $reason]);
            }

            return $this->success($result);
        });
    }

    private function checkType(Request $request): ?Response
    {
        if ($request->game->type !== 'self' && $request->game->type !== 'embedded') {
            return $this->fail('SDK not supported for this game type', 403);
        }
        return null;
    }
}
