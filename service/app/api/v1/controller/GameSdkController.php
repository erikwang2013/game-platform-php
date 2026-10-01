<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\event\EventBus;
use common\model\GamePlayLog;
use app\provider\ProviderFactory;
use app\provider\SelfProvider;
use app\service\AntiCheatService;
use app\service\GamePlayRecorder;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

/**
 * 自研/内嵌游戏 SDK 接口（M5）
 *
 * 与 /api/provider/* 相同的资金语义（SelfProvider，平台持有余额），
 * 认证基于 SDK 会话令牌（SdkSessionAuth），user_id 一律取自会话。
 */
#[Apidoc\Title("游戏 SDK")]
#[Apidoc\Group("game-sdk")]
class GameSdkController extends BaseController
{
    /**
     * 查询用户游戏余额
     * POST /api/game/balance
     */
    #[Apidoc\Url("/api/game/balance")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Returned(name: "balance", type: "string", desc: "游戏余额")]
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
    #[Apidoc\Url("/api/game/bet")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", require: true, desc: "下注金额（精确字符串，须大于 0）")]
    #[Apidoc\Param(name: "round_id", type: "string", require: true, desc: "局ID（必填：结算/退款的幂等与投注匹配都以它为键，下注时不传该局将永远无法结算）")]
    #[Apidoc\Param(name: "meta", type: "array", desc: "附加元数据")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "下注后余额")]
    public function bet(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        // M0: 写端点只认服务端令牌（缺省/旧令牌是 read，SdkSessionAuth 注入）。
        // M1 起服务端令牌的唯一来源是 /api/provider/session-token（ProviderAuth，需 game.api_secret）
        if ($request->sdkRole !== 'server') {
            return $this->fail(trans('SDK write requires server role'), 403);
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if (empty($sessionId)) {
            return $this->fail(trans('Invalid params'), 422);
        }
        // 金额语法闸（复用 SelfProvider 的那份实现）：'abc'/'1e5'/'+-100' 直接进 bccomp 抛 ValueError ⇒ 500。
        // 只拦语法非法的原文；合法但 ≤ 0 仍走下面原来的 Invalid params 语义，行为不变。
        if (!SelfProvider::isAmountSyntaxValid($amount)) {
            return $this->fail(trans('Invalid amount'), 422);
        }
        if (bccomp($amount, '0', 8) <= 0) {
            return $this->fail(trans('Invalid params'), 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $request, $currencyId, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->bet($request->userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                GamePlayRecorder::record($request, $request->userId, $request->gameId, $sessionId, $roundId, 'bet', $amount, $result['balance_after'] ?? '0', $meta);
            }

            return $this->success($result);
        }, 3);
    }

    /**
     * 通知结算
     * POST /api/game/settle
     */
    #[Apidoc\Url("/api/game/settle")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", require: true, desc: "结算金额（精确字符串，须大于 0，且同时不超过同 round 投注总额 × MAX_PAYOUT_MULTIPLIER（当前 100）与单 round 绝对上限 MAX_PAYOUT_PER_ROUND（当前 10000））")]
    #[Apidoc\Param(name: "round_id", type: "string", require: true, desc: "局ID（必填：幂等与投注匹配都以它为键，缺失/空串直接拒绝、不会退化成跳过幂等）")]
    #[Apidoc\Param(name: "meta", type: "array", desc: "附加元数据，result 字段用于事件映射")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "win_amount", type: "string", desc: "本局中奖金额")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "结算后余额")]
    public function settle(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        // M0: 同 bet —— 派奖是把钱加进余额的入口，非服务端令牌一律 403
        //（判据必须排在 ProviderFactory 之前，否则钱已动完才拒绝）
        if ($request->sdkRole !== 'server') {
            return $this->fail(trans('SDK write requires server role'), 403);
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if (empty($sessionId)) {
            return $this->fail(trans('Invalid params'), 422);
        }
        // 同 bet：语法非法的金额在入口就 fail-loud，别让它进 provider 后再抛 ValueError
        if (!SelfProvider::isAmountSyntaxValid($amount)) {
            return $this->fail(trans('Invalid amount'), 422);
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
        }, 3);
    }

    /**
     * 通知退款
     * POST /api/game/refund
     */
    #[Apidoc\Url("/api/game/refund")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", require: true, desc: "退款金额（精确字符串，须大于 0，且不超过同 round 投注总额 —— 退的是本金，不受派奖闸约束）")]
    #[Apidoc\Param(name: "round_id", type: "string", require: true, desc: "局ID（必填：与结算共用同一条幂等/投注匹配路径，缺失/空串直接拒绝）")]
    #[Apidoc\Param(name: "reason", type: "string", default: "unknown", desc: "退款原因")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "退款后余额")]
    public function refund(Request $request): Response
    {
        if ($r = $this->checkType($request)) {
            return $r;
        }
        // M0: 同 bet —— 退款同样是资金写入口
        if ($request->sdkRole !== 'server') {
            return $this->fail(trans('SDK write requires server role'), 403);
        }
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = (string) $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = (string) $request->input('round_id', '');
        $reason = (string) $request->input('reason', 'unknown');

        if (empty($sessionId)) {
            return $this->fail(trans('Invalid params'), 422);
        }
        if (!SelfProvider::isAmountSyntaxValid($amount)) {
            return $this->fail(trans('Invalid amount'), 422);
        }
        if (bccomp($amount, '0', 8) <= 0) {
            return $this->fail(trans('Invalid params'), 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $request, $currencyId, $sessionId, $amount, $roundId, $reason) {
            $result = $provider->refund($request->userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $reason);

            if ($result['success']) {
                GamePlayRecorder::record($request, $request->userId, $request->gameId, $sessionId, $roundId, 'refund', $amount, $result['balance_after'] ?? '0', ['reason' => $reason]);
            }

            return $this->success($result);
        }, 3);
    }

    private function checkType(Request $request): ?Response
    {
        if ($request->game->type !== 'self' && $request->game->type !== 'embedded') {
            return $this->fail(trans('SDK not supported for this game type'), 403);
        }
        return null;
    }
}
