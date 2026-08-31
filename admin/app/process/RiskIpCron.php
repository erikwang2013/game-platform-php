<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\model\IpReputation;
use app\model\RiskLog;
use support\Log;
use support\Redis;

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
    private string $lastRun = '';

    public function onWorkerStart(): void
    {
        Log::info('RiskIpCron started (daily maintenance, checked every 30min)');

        while (true) {
            try {
                if (date('G') === '3' && $this->lastRun !== date('Y-m-d')) {
                    self::runDaily();
                    $this->lastRun = date('Y-m-d');
                }
            } catch (\Throwable $e) {
                Log::error('RiskIpCron run failed: ' . $e->getMessage());
            }
            sleep(1800);
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
