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

    public static function write(int $userId, ?int $gameId, string $action, array $detail = [], string $ip = '', string $ua = ''): int
    {
        $id = self::snowflakeId();
        $now = date('Y-m-d H:i:s');

        $log = new GamePlayLog();
        $log->id = $id;
        $log->user_id = $userId;
        $log->game_id = $gameId ?? 0;
        $log->action = $action;
        $log->detail = $detail;
        $log->ip_address = $ip;
        $log->user_agent = $ua;
        $log->save();

        self::syncToClickHouse([[
            'id' => $id, 'user_id' => $userId, 'game_id' => $gameId ?? 0,
            'action' => $action, 'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip_address' => $ip, 'user_agent' => $ua,
            'created_at' => $now, 'updated_at' => $now,
        ]]);

        return $id;
    }

    public static function writeBatch(array $rows): int
    {
        if (empty($rows)) return 0;
        $now = date('Y-m-d H:i:s');
        $chRows = [];

        foreach ($rows as $row) {
            $id = self::snowflakeId();
            $log = new GamePlayLog();
            $log->id = $id;
            $log->user_id = $row['user_id'];
            $log->game_id = $row['game_id'] ?? 0;
            $log->action = $row['action'];
            $log->detail = $row['detail'] ?? [];
            $log->ip_address = $row['ip_address'] ?? '';
            $log->user_agent = $row['user_agent'] ?? '';
            $log->save();

            $chRows[] = [
                'id' => $id, 'user_id' => $log->user_id, 'game_id' => $log->game_id,
                'action' => $log->action, 'detail' => json_encode($log->detail, JSON_UNESCAPED_UNICODE),
                'ip_address' => $log->ip_address, 'user_agent' => $log->user_agent,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        self::syncToClickHouse($chRows);
        return count($rows);
    }

    private static function syncToClickHouse(array $rows): void
    {
        try {
            ClickHouseService::table('erik_game_play_log')->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('CH sync fail: ' . $e->getMessage());
        }
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(
                workerId: (int)($cfg['worker_id'] ?? 1),
                datacenterId: (int)($cfg['datacenter_id'] ?? 1),
                epoch: $cfg['start_timestamp'] ?? null,
            );
        }
        return self::$snowflake->nextId();
    }
}
