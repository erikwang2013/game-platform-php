<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\model\GamePlayLog;
use support\Redis;

class GameSessionService
{
    const SESSION_TTL = 900; // 15 minutes
    const KEY_PREFIX = 'game_session:';

    public static function heartbeat(int $userId, int $gameId, string $sessionId): void
    {
        $key = self::KEY_PREFIX . $sessionId;
        Redis::setex($key, self::SESSION_TTL, json_encode([
            'user_id' => $userId,
            'game_id' => $gameId,
            'last_heartbeat' => time(),
        ]));
    }

    public static function isActive(string $sessionId): bool
    {
        return Redis::exists(self::KEY_PREFIX . $sessionId) > 0;
    }

    public static function getSession(string $sessionId): ?array
    {
        $data = Redis::get(self::KEY_PREFIX . $sessionId);
        if (!$data) return null;
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function endSession(string $sessionId): void
    {
        Redis::del(self::KEY_PREFIX . $sessionId);
        GamePlayLog::where('session_id', $sessionId)
            ->where('action', 'start')
            ->update(['ended_at' => date('Y-m-d H:i:s')]);
    }

    public static function expireStaleSessions(): int
    {
        $count = 0;
        $logs = GamePlayLog::where('action', 'start')
            ->whereNull('ended_at')
            ->where('started_at', '<', date('Y-m-d H:i:s', time() - self::SESSION_TTL))
            ->get();

        foreach ($logs as $log) {
            if (!self::isActive($log->session_id)) {
                GamePlayLog::where('id', $log->id)
                    ->update(['ended_at' => date('Y-m-d H:i:s')]);
                $count++;
            }
        }
        return $count;
    }
}
