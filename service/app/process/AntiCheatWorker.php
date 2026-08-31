<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\service\AntiCheatService;
use support\Log;

/**
 * 反作弊批处理进程：每小时按 id 游标增量扫描 bet/settle 日志。
 * ponytail: 游标文件仅单实例可靠（process.php count=1）；多实例需换 Redis 原子游标。
 */
class AntiCheatWorker
{
    private const BATCH_LIMIT = 5000;

    public function onWorkerStart(): void
    {
        Log::info('AntiCheatWorker started');

        while (true) {
            try {
                $cursorFile = runtime_path() . '/anticheat_cursor';
                $since = (int) @file_get_contents($cursorFile);
                $next = AntiCheatService::runBatch($since, self::BATCH_LIMIT);

                if ($next > $since) {
                    file_put_contents($cursorFile, (string) $next);
                    Log::info('anticheat batch done', ['from' => $since, 'to' => $next]);
                }
            } catch (\Throwable $e) {
                Log::error('anticheat batch failed', ['error' => $e->getMessage()]);
            }

            sleep(3600);
        }
    }
}
