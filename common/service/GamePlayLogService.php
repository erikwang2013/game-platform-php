<?php

declare(strict_types=1);

namespace common\service;

use common\model\GamePlayLog;
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
use Erikwang2013\Snowflake\Snowflake;
use support\Log;

class GamePlayLogService
{
    private static ?Snowflake $snowflake = null;

    public static function write(int $userId, int $gameId, string $action, array $detail = [], string $ip = '', string $ua = ''): int
    {
        $id = self::snowflakeId();
        $now = date('Y-m-d H:i:s');

        $log = new GamePlayLog();
        $log->id = $id;
        $log->user_id = $userId;
        $log->game_id = $gameId;
        $log->action = $action;
        $log->detail = $detail;
        $log->ip_address = $ip;
        $log->user_agent = $ua;
        $log->save();

        self::syncToClickHouse([[
            'id' => $id, 'user_id' => $userId, 'game_id' => $gameId,
            'action' => $action, 'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip_address' => $ip, 'user_agent' => $ua,
            'created_at' => $now, 'updated_at' => $now,
        ]]);

        return $id;
    }

    private static function syncToClickHouse(array $rows): void
    {
        try {
            ClickHouseService::table('erik_game_play_log')->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('CH game_play_log sync fail: ' . $e->getMessage());
        }
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(
                workerId: (int)($cfg['worker_id'] ?? 1),
                datacenterId: (int)($cfg['datacenter_id'] ?? 1),
            );
        }
        return self::$snowflake->nextId();
    }
}
