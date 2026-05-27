<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\model\GamePlayLog;
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
use Erikwang2013\Snowflake\Snowflake;
use support\Log;

/**
 * 游戏行为日志服务
 *
 * MySQL 为主存储（ACID），ClickHouse 为分析存储。
 * 写入时先 MySQL（保证一致性），再同步 ClickHouse（失败不阻断）。
 */
class GamePlayLogService
{
    private static ?Snowflake $snowflake = null;

    /**
     * 写入单条日志
     */
    public static function write(int $userId, ?int $gameId, string $action, array $detail = [], string $ipAddress = '', string $userAgent = ''): int
    {
        $now = date('Y-m-d H:i:s');
        $id = self::snowflakeId();

        $log = new GamePlayLog();
        $log->id         = $id;
        $log->user_id    = $userId;
        $log->game_id    = $gameId ?? 0;
        $log->action     = $action;
        $log->detail     = $detail;
        $log->ip_address = $ipAddress;
        $log->user_agent = $userAgent;
        $log->save();

        self::syncToClickHouse([[
            'id'         => $id,
            'user_id'    => $userId,
            'game_id'    => $gameId ?? 0,
            'action'     => $action,
            'detail'     => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);

        return $id;
    }

    /**
     * 批量写入日志
     *
     * @param array[] $rows [['user_id' => int, 'game_id' => ?int, 'action' => string, ...], ...]
     * @return int 写入条数
     */
    public static function writeBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $clickHouseRows = [];

        foreach ($rows as $row) {
            $id = self::snowflakeId();

            $log = new GamePlayLog();
            $log->id         = $id;
            $log->user_id    = $row['user_id'];
            $log->game_id    = $row['game_id'] ?? 0;
            $log->action     = $row['action'];
            $log->detail     = $row['detail'] ?? [];
            $log->ip_address = $row['ip_address'] ?? '';
            $log->user_agent = $row['user_agent'] ?? '';
            $log->save();

            $clickHouseRows[] = [
                'id'         => $id,
                'user_id'    => $log->user_id,
                'game_id'    => $log->game_id,
                'action'     => $log->action,
                'detail'     => json_encode($log->detail, JSON_UNESCAPED_UNICODE),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        self::syncToClickHouse($clickHouseRows);

        return count($rows);
    }

    /**
     * 同步到 ClickHouse（失败不阻断主流程）
     */
    private static function syncToClickHouse(array $rows): void
    {
        try {
            ClickHouseService::table('erik_game_play_log')->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('ClickHouse sync failed for game_play_log: ' . $e->getMessage());
        }
    }

    private static function snowflakeId(): int
    {
        if (self::$snowflake === null) {
            $cfg = config('snowflake', []);
            self::$snowflake = new Snowflake(
                workerId: (int) ($cfg['worker_id'] ?? 1),
                datacenterId: (int) ($cfg['datacenter_id'] ?? 1),
                epoch: $cfg['start_timestamp'] ?? null,
            );
        }

        return self::$snowflake->nextId();
    }
}
