<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use common\model\IpReputation;
use common\model\RiskLog;
use support\Log;
use support\Redis;
use Workerman\Timer;

/**
 * 风控定时维护（H4 P8 最小版，每日 03:00 执行一次）：
 *   1) 信誉衰减：90 天未再见的非白名单低分 IP 恢复中性分（50），并清理信誉缓存；
 *   2) 日志保留：清理 180 天前 risk_log（§8.2 保留策略）。
 *
 * ponytail: 方案原为 service 侧每日进程（含外部代理/VPN 检测源刷新），
 *           此处落地为管理端最小版；外部检测源刷新待外部服务接入。
 */
class RiskIpCron
{
    /** 检查间隔（秒）：每 30 分钟看一次是否进入 03:00 窗口 */
    private const CHECK_INTERVAL = 1800;

    private string $lastRun = '';

    public function onWorkerStart(): void
    {
        Log::info('RiskIpCron started (daily maintenance, checked every 30min)');

        // 定时器而非 while(true)+sleep(1800)：onWorkerStart 必须尽快返回。workerman 的
        // Worker::run() 先 reinstallSignal()（把子进程的信号处理挂到事件循环上），
        // 再在 onWorkerStart 返回【之后】才 $globalEvent->run() ⇒ 阻塞在这里信号永不派发，
        // graceful stop（SIGINT/SIGQUIT）失效、只能 SIGKILL。
        // 首轮保留「启动即检查一次」语义：$lastRun 是进程内存状态、重启即丢，少了这一次，
        // 03:05 重启的进程要等到 03:35 才检查，当天 03:00 窗口就整天空过。
        Timer::add(1, [$this, 'tick'], [], false);
        Timer::add(self::CHECK_INTERVAL, [$this, 'tick']);
    }

    public function tick(): void
    {
        try {
            if (date('G') === '3' && $this->lastRun !== date('Y-m-d')) {
                self::runDaily();
                $this->lastRun = date('Y-m-d');
            }
        } catch (\Throwable $e) {
            Log::error('RiskIpCron run failed: ' . $e->getMessage());
        }
    }

    private static function runDaily(): void
    {
        // 1) 信誉衰减：黑名单 IP 长期未见 → 回到中性分
        $stale = IpReputation::where('source', '!=', 'internal_whitelist')
            ->where('reputation_score', '<', 50)
            ->where('last_seen_at', '<', date('Y-m-d H:i:s', time() - 90 * 86400))
            ->get();
        foreach ($stale as $row) {
            $row->reputation_score = 50;
            $row->save();
            try {
                Redis::del('risk:ip_rep:' . $row->ip_hash);
            } catch (\Throwable) {
                // 缓存删不掉则随 TTL 自然过期
            }
        }

        // 2) 180 天日志清理（分批删，避免长事务/锁表）
        $cutoff = date('Y-m-d H:i:s', time() - 180 * 86400);
        $cleaned = 0;
        do {
            $deleted = RiskLog::where('created_at', '<', $cutoff)->limit(1000)->delete();
            $cleaned += $deleted;
        } while ($deleted >= 1000);

        Log::info(sprintf('RiskIpCron done: decayed=%d cleaned_risk_log=%d', count($stale), $cleaned));
    }
}
