<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\service\AntiCheatService;
use support\Log;
use Workerman\Timer;

/**
 * 反作弊批处理进程：每小时按 id 游标增量扫描 bet/settle 日志。
 * ponytail: 游标文件仅单实例可靠（process.php count=1）；多实例需换 Redis 原子游标。
 */
class AntiCheatWorker
{
    private const BATCH_LIMIT = 5000;
    /** 批处理间隔（秒） */
    private const INTERVAL = 3600;

    public function onWorkerStart(): void
    {
        Log::info('AntiCheatWorker started');

        // 定时器而非 while(true)+sleep(3600)：onWorkerStart 必须尽快返回。workerman 的
        // Worker::run() 先 reinstallSignal()（把子进程的信号处理挂到事件循环上），
        // 再在 onWorkerStart 返回【之后】才 $globalEvent->run() ⇒ 阻塞在这里信号永不派发，
        // graceful stop（SIGINT/SIGQUIT）失效、只能 SIGKILL。
        // 首轮仍保留「启动即补跑」语义（原 while 首轮不等待），用一次性 1s 延迟换不阻塞启动。
        Timer::add(1, [$this, 'runBatch'], [], false);
        Timer::add(self::INTERVAL, [$this, 'runBatch']);
    }

    public function runBatch(): void
    {
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
    }
}
