<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\DepositOrder;
use app\model\WithdrawOrder;
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
}
