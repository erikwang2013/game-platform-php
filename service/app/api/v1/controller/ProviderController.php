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
                $this->logPlay($request, $userId, $request->gameId, $sessionId, $roundId, 'bet', $amount, $result['balance_after'] ?? '0', $meta);
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
                $this->logPlay($request, $userId, $request->gameId, $sessionId, $roundId, 'settle', $winAmount, $result['balance_after'] ?? '0', $meta);

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
                $this->logPlay($request, $userId, $request->gameId, $sessionId, $roundId, 'refund', $amount, $result['balance_after'] ?? '0', ['reason' => $reason]);
            }

            return $this->success($result);
        });
    }

    private function logPlay(Request $request, int $userId, int $gameId, string $sessionId, string $roundId, string $action, string $amount, string $balanceAfter, array $meta): void
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
        // 反作弊所需: 按 action 拆分注码/赢额
        if ($action === 'bet') {
            $log->bet_amount = $amount;
            $log->win_amount = null;
        } elseif ($action === 'settle') {
            $log->bet_amount = null;
            $log->win_amount = $amount;
        } elseif ($action === 'refund') {
            $log->bet_amount = null;
            $log->win_amount = $amount;
        } else {
            $log->bet_amount = null;
            $log->win_amount = null;
        }
        $log->metadata = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $log->started_at = date('Y-m-d H:i:s');
        if (isset($meta['ended_at'])) {
            $log->ended_at = $meta['ended_at'];
        }
        // 反作弊列（H5 评审修订 #1）：PII 只存 sha256，device_id 可明文；IP/UA 优先取游戏转发 meta，兜底取请求方
        $ip = (string) ($meta['ip'] ?? $request->getRealIp());
        $ua = (string) ($meta['user_agent'] ?? $request->header('User-Agent', ''));
        $log->ip_hash = $ip !== '' ? hash('sha256', $ip) : '';
        $log->user_agent_hash = $ua !== '' ? hash('sha256', $ua) : '';
        $log->device_id = (string) ($meta['device_id'] ?? '');
        $log->result = (string) ($meta['result'] ?? '');
        $log->level_id = isset($meta['level_id']) ? (int) $meta['level_id'] : null;
        $log->move_count = isset($meta['move_count']) ? (int) $meta['move_count'] : null;
        $log->ended_at_round = !empty($meta['ended_at']) ? (string) $meta['ended_at'] : null;
        $log->save();
    }
}
