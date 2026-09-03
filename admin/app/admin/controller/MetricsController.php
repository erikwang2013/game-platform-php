<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use common\BcMath;
use common\model\DepositOrder;
use common\model\WithdrawOrder;
use GuzzleHttp\Client;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

/**
 * @Apidoc\Title("监控指标")
 * @Apidoc\Group("metrics")
 */
class MetricsController
{
    private const BIZ_CACHE_TTL = 30;
    private const EVENT_EMIT_KEY = 'metrics:event_emit_total';
    private const EVENT_CONSUME_KEY = 'metrics:event_consume_total';

    /**
     * @Apidoc\Title("Prometheus指标")
     * @Apidoc\Desc("返回Prometheus格式的监控指标数据")
     * @Apidoc\Url("/metrics")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     */
    public function index(Request $request): Response
    {
        $metrics = [];

        $activeUsers = $this->safeCount(function () {
            return AdminUser::whereDate('last_login_at', date('Y-m-d'))->count();
        });
        $metrics[] = '# HELP open_admin_active_users Active users today';
        $metrics[] = '# TYPE open_admin_active_users gauge';
        $metrics[] = "open_admin_active_users {$activeUsers}";

        $totalUsers = $this->safeCount(function () {
            return AdminUser::count();
        });
        $metrics[] = '# HELP open_admin_total_users Total registered users';
        $metrics[] = '# TYPE open_admin_total_users gauge';
        $metrics[] = "open_admin_total_users {$totalUsers}";

        try {
            Db::select('SELECT 1');
            $dbStatus = 1;
        } catch (Throwable) {
            $dbStatus = 0;
        }
        $metrics[] = '# HELP open_admin_db_up Database connection status (1=up, 0=down)';
        $metrics[] = '# TYPE open_admin_db_up gauge';
        $metrics[] = "open_admin_db_up {$dbStatus}";

        try {
            Redis::ping();
            $redisStatus = 1;
        } catch (Throwable) {
            $redisStatus = 0;
        }
        $metrics[] = '# HELP open_admin_redis_up Redis connection status (1=up, 0=down)';
        $metrics[] = '# TYPE open_admin_redis_up gauge';
        $metrics[] = "open_admin_redis_up {$redisStatus}";

        $memoryBytes = memory_get_usage(true);
        $metrics[] = '# HELP open_admin_memory_usage_bytes PHP memory usage in bytes';
        $metrics[] = '# TYPE open_admin_memory_usage_bytes gauge';
        $metrics[] = "open_admin_memory_usage_bytes {$memoryBytes}";

        $pendingWithdraws = $this->cachedGauge('metrics:biz:withdraw_pending', function () {
            return WithdrawOrder::where('status', 'pending')->count();
        });
        $metrics[] = '# HELP open_admin_withdraw_pending Pending withdrawal orders awaiting review';
        $metrics[] = '# TYPE open_admin_withdraw_pending gauge';
        $metrics[] = "open_admin_withdraw_pending {$pendingWithdraws}";

        $depositsToday = $this->cachedGauge('metrics:biz:deposit_today', function () {
            return DepositOrder::where('status', 'confirmed')
                ->whereDate('paid_at', date('Y-m-d'))
                ->count();
        });
        $metrics[] = '# HELP open_admin_deposit_confirmed_today Confirmed deposit orders paid today';
        $metrics[] = '# TYPE open_admin_deposit_confirmed_today gauge';
        $metrics[] = "open_admin_deposit_confirmed_today {$depositsToday}";

        $emitTotal = $this->redisCounter(self::EVENT_EMIT_KEY);
        $consumeTotal = $this->redisCounter(self::EVENT_CONSUME_KEY);
        $metrics[] = '# HELP open_admin_event_emit_total Events published to Redis Pub/Sub';
        $metrics[] = '# TYPE open_admin_event_emit_total counter';
        $metrics[] = "open_admin_event_emit_total {$emitTotal}";
        $metrics[] = '# HELP open_admin_event_consume_total Events consumed by a subscriber process';
        $metrics[] = '# TYPE open_admin_event_consume_total counter';
        $metrics[] = "open_admin_event_consume_total {$consumeTotal}";

        // ---- L4 可观测性扩充：业务组 ----
        $depositsToday = $this->cachedGauge('metrics:biz:deposit_total_today', function () {
            return DepositOrder::whereDate('created_at', date('Y-m-d'))->count();
        });
        $metrics[] = '# HELP open_admin_deposit_total_today Total deposit orders created today';
        $metrics[] = '# TYPE open_admin_deposit_total_today gauge';
        $metrics[] = "open_admin_deposit_total_today {$depositsToday}";
        $metrics[] = '# HELP open_admin_deposit_success_rate_percent Confirmed/total deposit ratio today (percent)';
        $metrics[] = '# TYPE open_admin_deposit_success_rate_percent gauge';
        $metrics[] = 'open_admin_deposit_success_rate_percent ' . ($depositsToday > 0 ? (float) BcMath::percent((string) $this->safeCount(function () {
            return DepositOrder::where('status', 'confirmed')->whereDate('created_at', date('Y-m-d'))->count();
        }), (string) $depositsToday, 2) : 0);

        $diffPending = $this->cachedGauge('metrics:biz:reconciliation_diff_pending', function () {
            // 对账差异表可能尚未建表：safeCount 兜底返回 0
            return (int) Db::table('game_reconciliation_diff')->where('status', 'pending')->count();
        });
        $metrics[] = '# HELP open_admin_reconciliation_diff_pending Pending reconciliation diffs (H3 money risk)';
        $metrics[] = '# TYPE open_admin_reconciliation_diff_pending gauge';
        $metrics[] = "open_admin_reconciliation_diff_pending {$diffPending}";

        // ---- L4 可观测性扩充：系统组 ----
        $metrics[] = '# HELP open_admin_cpu_load_1m System 1-minute load average';
        $metrics[] = '# TYPE open_admin_cpu_load_1m gauge';
        $metrics[] = 'open_admin_cpu_load_1m ' . $this->floatValue(function () {
            $load = explode(' ', (string) @file_get_contents('/proc/loadavg'));
            return (float) ($load[0] ?? 0);
        });

        $metrics[] = '# HELP open_admin_process_fd_count Open file descriptors of this process';
        $metrics[] = '# TYPE open_admin_process_fd_count gauge';
        $metrics[] = 'open_admin_process_fd_count ' . $this->safeCount(function () {
            $fds = @scandir('/proc/self/fd');
            return is_array($fds) ? count($fds) - 2 : 0;
        });

        $metrics[] = '# HELP open_admin_uptime_seconds System uptime in seconds';
        $metrics[] = '# TYPE open_admin_uptime_seconds gauge';
        $metrics[] = 'open_admin_uptime_seconds ' . $this->safeCount(function () {
            $uptime = explode(' ', (string) @file_get_contents('/proc/uptime'));
            return (int) ($uptime[0] ?? 0);
        });

        // ---- L4 可观测性扩充：基础设施组 ----
        $mysqlConns = $this->cachedGauge('metrics:infra:mysql_connections', function () {
            $rows = Db::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($rows[0]->Value ?? 0);
        });
        $metrics[] = '# HELP open_admin_mysql_connections MySQL active connections';
        $metrics[] = '# TYPE open_admin_mysql_connections gauge';
        $metrics[] = "open_admin_mysql_connections {$mysqlConns}";

        $redisInfo = $this->redisInfoCached();
        $metrics[] = '# HELP open_admin_redis_hit_rate_percent Redis keyspace hit rate (percent)';
        $metrics[] = '# TYPE open_admin_redis_hit_rate_percent gauge';
        $metrics[] = 'open_admin_redis_hit_rate_percent ' . ($redisInfo['hit_rate'] ?? 0);
        $metrics[] = '# HELP open_admin_redis_memory_bytes Redis used memory in bytes';
        $metrics[] = '# TYPE open_admin_redis_memory_bytes gauge';
        $metrics[] = 'open_admin_redis_memory_bytes ' . ($redisInfo['used_memory'] ?? 0);

        $esUp = $this->cachedGauge('metrics:infra:es_up', function () {
            try {
                $hosts = (array) config('scout.hosts', []);
                if (!$hosts) {
                    return 0;
                }
                $resp = (new Client(['timeout' => 2]))->get((string) $hosts[0]);
                return $resp->getStatusCode() === 200 ? 1 : 0;
            } catch (Throwable) {
                return 0;
            }
        });
        $metrics[] = '# HELP open_admin_es_up Elasticsearch reachability (1=up, 0=down)';
        $metrics[] = '# TYPE open_admin_es_up gauge';
        $metrics[] = "open_admin_es_up {$esUp}";

        $metrics[] = '# HELP open_admin_info Application info';
        $metrics[] = '# TYPE open_admin_info gauge';
        $metrics[] = 'open_admin_info{version="1.1",php="' . PHP_VERSION . '"} 1';

        return response(implode("\n", $metrics) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * @param callable():int $compute
     */
    private function cachedGauge(string $key, callable $compute): int
    {
        try {
            $cached = Redis::get($key);
            if ($cached !== false && $cached !== null && $cached !== '') {
                return (int) $cached;
            }
        } catch (Throwable) {
        }

        $value = $this->safeCount($compute);

        try {
            Redis::setex($key, self::BIZ_CACHE_TTL, (string) $value);
        } catch (Throwable) {
        }

        return $value;
    }

    /**
     * @param callable():int $compute
     */
    private function safeCount(callable $compute): int
    {
        try {
            return (int) $compute();
        } catch (Throwable) {
            return 0;
        }
    }

    private function redisCounter(string $key): int
    {
        try {
            return (int) (Redis::get($key) ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param callable():float $compute
     */
    private function floatValue(callable $compute): float
    {
        try {
            return (float) $compute();
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * Redis INFO 关键指标，30s 缓存（键 metrics:infra:redis_info_json）。
     *
     * @return array{hit_rate: int, used_memory: int}
     */
    private function redisInfoCached(): array
    {
        try {
            $cached = Redis::get('metrics:infra:redis_info_json');
            if ($cached !== false && $cached !== null && $cached !== '') {
                return json_decode($cached, true) ?: ['hit_rate' => 0, 'used_memory' => 0];
            }
        } catch (Throwable) {
        }

        $info = ['hit_rate' => 0, 'used_memory' => 0];
        try {
            $stats   = Redis::info('stats');
            $hits    = (int) ($stats['keyspace_hits'] ?? 0);
            $misses  = (int) ($stats['keyspace_misses'] ?? 0);
            if ($hits + $misses > 0) {
                $info['hit_rate'] = (int) BcMath::percent((string) $hits, (string) ($hits + $misses), 0);
            }
            $memory = Redis::info('memory');
            $info['used_memory'] = (int) ($memory['used_memory'] ?? 0);
            Redis::setex('metrics:infra:redis_info_json', self::BIZ_CACHE_TTL, json_encode($info));
        } catch (Throwable) {
        }

        return $info;
    }
}
