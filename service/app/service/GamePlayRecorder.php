<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\model\GamePlayLog;
use common\SnowflakeService;
use Webman\Http\Request;

/**
 * 对局流水记录（M5 抽取）：ProviderController（第三方回调）与 GameSdkController（自研/内嵌 SDK）共用
 * 反作弊列口径一致：PII 只存 sha256，device_id 可明文；IP/UA 优先取游戏 meta，兜底取请求方。
 */
class GamePlayRecorder
{
    public static function record(Request $request, int $userId, int $gameId, string $sessionId, string $roundId, string $action, string $amount, string $balanceAfter, array $meta): void
    {
        $log = new GamePlayLog();
        $log->id = SnowflakeService::generate();
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
