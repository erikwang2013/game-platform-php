<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use support\Db;
use support\Log;
use support\Redis;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 健康探活进程（L4 可观测性，区别于 Monitor 热重载监视器）：
 * 每分钟探测 MySQL/Redis，失败写入错误日志并把指标置 0（Redis 键 health:mysql / health:redis，
 * 供 Prometheus 抓取或告警规则消费；Redis 本身不可用时只落日志）。
 * 注意：ES 探活由 admin 侧 MetricsController 的 es_up gauge 承担（service 侧无 ES 配置）。
 */
class Health
{
    public function onWorkerStart(Worker $worker): void
    {
        Timer::add(60, function () {
            $dbOk = $this->probe(fn () => Db::select('SELECT 1'));
            $redisOk = $this->probe(fn () => Redis::ping());

            if (!$dbOk) {
                Log::error('Health probe failed: MySQL unreachable');
            }
            if (!$redisOk) {
                Log::error('Health probe failed: Redis unreachable');
            }

            // 指标写入失败时静默（Redis 已不可用，日志已记录）
            try {
                Redis::setex('health:mysql', 180, $dbOk ? '1' : '0');
                Redis::setex('health:redis', 180, $redisOk ? '1' : '0');
            } catch (Throwable) {
            }
        });
    }

    private function probe(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }
}
