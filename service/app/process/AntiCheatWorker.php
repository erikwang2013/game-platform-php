<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\service\AntiCheatService;
use support\Log;
use support\Redis;
use Workerman\Timer;

/**
 * 反作弊批处理进程：每小时按 id 游标增量扫描 bet/settle 日志。
 *
 * service 是多实例部署（N 台各跑一份 process.php）⇒ 游标与「谁跑」都必须跨实例，
 * 不能是进程内状态或本机文件：本机文件在 N 台下各读各的，每台都从 0 扫同一段日志，
 * 而 AntiCheatService::runBatch 是逐行累加（daily_stat 计数翻 N 倍、detect 重复命中）。
 */
class AntiCheatWorker
{
    private const BATCH_LIMIT = 5000;
    /** 批处理间隔（秒） */
    private const INTERVAL = 3600;
    /** 共享游标键：已处理到的最大 game_play_log.id，N 台实例共用一份进度 */
    private const CURSOR_KEY = 'anticheat:cursor';
    /** 批处理互斥键：同一小时只允许一台实例跑（SET NX EX，与 WithdrawController:78 同一原语） */
    private const LOCK_KEY = 'anticheat:batch:lock';
    /**
     * 锁 TTL（秒）：只当崩溃兜底、跑完不显式释放（一小时才争一次，不需要属主 token + Lua 删锁那套，
     * 见 WithdrawController::releaseLockIfOwned）。取值须 > 最慢一批（5000 行），且 < INTERVAL。
     */
    private const LOCK_TTL = 1800;

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
            // 抢不到锁 = 本小时已有别的实例在做，直接返回（不是失败，不刷日志）
            if (!Redis::set(self::LOCK_KEY, bin2hex(random_bytes(8)), 'EX', self::LOCK_TTL, 'NX')) {
                return;
            }

            $since = (int) Redis::get(self::CURSOR_KEY);
            $next = AntiCheatService::runBatch($since, self::BATCH_LIMIT);

            if ($next > $since) {
                Redis::set(self::CURSOR_KEY, (string) $next);
                Log::info('anticheat batch done', ['from' => $since, 'to' => $next]);
            }
        } catch (\Throwable $e) {
            // fail-closed：Redis 不可达时不抢锁也不推游标 ⇒ 本小时不跑（每小时一批，丢一批可接受），
            // 绝不退回「无锁照跑」——那正是多实例下重复干活的原因
            Log::error('anticheat batch failed', ['error' => $e->getMessage()]);
        }
    }
}
