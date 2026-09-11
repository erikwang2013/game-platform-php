<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\event\EventBus;
use common\model\GamePlayLog;
use app\provider\ProviderFactory;
use app\service\AntiCheatService;
use app\service\GamePlayRecorder;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

/**
 * 游戏提供商回调接口
 *
 * 第三方游戏通过此 API 与平台交互（查余额、下注、结算、退款）。
 * 所有接口需 ProviderAuth 中间件验证 HMAC-SHA256 签名。
 */
#[Apidoc\Title("游戏提供商回调")]
#[Apidoc\Group("provider")]
class ProviderController extends BaseController
{
    /**
     * 查询用户余额
     * POST /api/provider/balance
     */
    #[Apidoc\Url("/api/provider/balance")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "user_id", type: "int", require: true, desc: "用户ID")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Returned(name: "balance", type: "string", desc: "用户余额")]
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
    #[Apidoc\Url("/api/provider/bet")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "user_id", type: "int", require: true, desc: "用户ID")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", require: true, desc: "下注金额（精确字符串，须大于 0）")]
    #[Apidoc\Param(name: "round_id", type: "string", desc: "局ID")]
    #[Apidoc\Param(name: "meta", type: "array", desc: "附加元数据")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "下注后余额")]
    public function bet(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if ($userId <= 0 || empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $currencyId, $request, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->bet($userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                GamePlayRecorder::record($request, $userId, $request->gameId, $sessionId, $roundId, 'bet', $amount, $result['balance_after'] ?? '0', $meta);
            }

            return $this->success($result);
        });
    }

    /**
     * 通知结算
     * POST /api/provider/settle
     */
    #[Apidoc\Url("/api/provider/settle")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "user_id", type: "int", require: true, desc: "用户ID")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", desc: "结算金额（精确字符串）")]
    #[Apidoc\Param(name: "round_id", type: "string", desc: "局ID")]
    #[Apidoc\Param(name: "meta", type: "array", desc: "附加元数据，result 字段用于反作弊事件")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "win_amount", type: "string", desc: "本局中奖金额")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "结算后余额")]
    public function settle(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $meta = $request->input('meta', []);

        if ($userId <= 0 || empty($sessionId)) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $currencyId, $request, $sessionId, $amount, $roundId, $meta) {
            $result = $provider->settle($userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $meta);

            if ($result['success']) {
                $winAmount = $result['win_amount'] ?? '0';
                GamePlayRecorder::record($request, $userId, $request->gameId, $sessionId, $roundId, 'settle', $winAmount, $result['balance_after'] ?? '0', $meta);

                // Update session ended_at
                GamePlayLog::where('session_id', $sessionId)
                    ->where('action', 'start')
                    ->update(['ended_at' => date('Y-m-d H:i:s')]);

                // 对局结算完成 → 反作弊旁路（非可靠事件走 Redis Pub/Sub，EventConsumer 内隔离异常不阻塞主链路）
                EventBus::emit(AntiCheatService::EVENT_ROUND_FINISHED, [
                    'user_id'    => $userId,
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
     * POST /api/provider/refund
     */
    #[Apidoc\Url("/api/provider/refund")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "user_id", type: "int", require: true, desc: "用户ID")]
    #[Apidoc\Param(name: "currency_id", type: "int", desc: "货币ID，0 表示默认货币")]
    #[Apidoc\Param(name: "session_id", type: "string", require: true, desc: "游戏会话ID")]
    #[Apidoc\Param(name: "amount", type: "string", require: true, desc: "退款金额（精确字符串，须大于 0）")]
    #[Apidoc\Param(name: "round_id", type: "string", desc: "局ID")]
    #[Apidoc\Param(name: "reason", type: "string", default: "unknown", desc: "退款原因")]
    #[Apidoc\Returned(name: "success", type: "boolean", desc: "是否成功")]
    #[Apidoc\Returned(name: "balance_after", type: "string", desc: "退款后余额")]
    public function refund(Request $request): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $currencyId = (int) $request->input('currency_id', 0);
        $sessionId = $request->input('session_id', '');
        $amount = $request->input('amount', '0');
        $roundId = $request->input('round_id', '');
        $reason = $request->input('reason', 'unknown');

        if ($userId <= 0 || empty($sessionId) || bccomp($amount, '0', 8) <= 0) {
            return $this->fail('Invalid params', 422);
        }

        $provider = ProviderFactory::create($request->game);

        return Db::transaction(function () use ($provider, $userId, $currencyId, $request, $sessionId, $amount, $roundId, $reason) {
            $result = $provider->refund($userId, $request->gameId, $currencyId, $sessionId, $amount, $roundId, $reason);

            if ($result['success']) {
                GamePlayRecorder::record($request, $userId, $request->gameId, $sessionId, $roundId, 'refund', $amount, $result['balance_after'] ?? '0', ['reason' => $reason]);
            }

            return $this->success($result);
        });
    }

}
