<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\common\SnowflakeService;
use app\model\GamePlayLog;
use Throwable;

/**
 * 游戏行为日志写入服务 — 落库 game_game_play_log（MySQL），供运营后台实时聚合分析
 */
class GamePlayLogService
{
    /**
     * @param int    $userId   用户 ID
     * @param int    $gameId   游戏 ID
     * @param string $action   start/end/earn/spend
     * @param array  $data     扩展数据: session_id/server_id/金额变动等
     * @param string $ip       来源 IP
     * @param string $userAgent User-Agent
     */
    public static function write(int $userId, int $gameId, string $action, array $data, string $ip, string $userAgent): void
    {
        try {
            $log = new GamePlayLog();
            $log->id = SnowflakeService::generate();
            $log->user_id = $userId;
            $log->game_id = $gameId;
            $log->server_id = (int) ($data['server_id'] ?? 0);
            $log->session_id = (string) ($data['session_id'] ?? '');
            $log->action = $action;
            $log->game_amount_before = (string) ($data['game_amount_before'] ?? '0');
            $log->game_amount_change = (string) ($data['game_amount_change'] ?? '0');
            $log->game_amount_after = (string) ($data['game_amount_after'] ?? '0');
            $log->platform_amount_change = (string) ($data['platform_amount_change'] ?? '0');
            unset($data['server_id'], $data['session_id'], $data['game_amount_before'],
                $data['game_amount_change'], $data['game_amount_after'], $data['platform_amount_change']);
            $data['ip'] = $ip;
            $data['user_agent'] = $userAgent;
            $log->metadata = json_encode($data, JSON_UNESCAPED_UNICODE);
            // 反作弊列双写（H5）：metadata 保留明文供运营查询，独立列只存 sha256
            $log->ip_hash = $ip !== '' ? hash('sha256', $ip) : '';
            $log->user_agent_hash = $userAgent !== '' ? hash('sha256', $userAgent) : '';
            $log->started_at = $data['started_at'] ?? date('Y-m-d H:i:s');
            $log->save();
        } catch (Throwable) {
            // 日志写入失败不影响主流程（游戏行为照常执行）
        }
    }
}
