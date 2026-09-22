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
 * ① 每分钟探测 MySQL/Redis，失败写入错误日志并把指标置 0（Redis 键 health:mysql / health:redis，
 *    供 Prometheus 抓取或告警规则消费；Redis 本身不可用时只落日志）。
 * ② 每小时做一次提现完成事件对账（见 reconcileWithdrawEvents()，指标 health:withdraw_event_gap）。
 * 注意：ES 探活由 admin 侧 MetricsController 的 es_up gauge 承担（service 侧无 ES 配置）。
 */
class Health
{
    /** 提现事件对账窗口（天）：只看近期完成单，窗口内计数归零即代表生产者在发事件 */
    private const GAP_WINDOW_DAYS = 30;
    /** 对账巡检间隔（秒）：提现完成是低频事件，无需与 60s 探活同频 */
    private const GAP_INTERVAL = 3600;
    /** 对账指标键：窗口内「已完成但 outbox 无对应事件行」的订单数 */
    private const GAP_METRIC_KEY = 'health:withdraw_event_gap';

    /**
     * 对账 SQL：total = 窗口内完成单数（分母，为 0 时结论未知）；gap = 其中 outbox 无事件行的数量。
     * 判据必须保持 LEFT JOIN —— 换成 INNER JOIN 会把缺行吃掉、gap 恒 0，整套巡检退化为假绿灯。
     * public 是为了让无库断言能钉住这个判据（tests/WithdrawEventReconcileContractTest）。
     */
    public const GAP_SQL = <<<'SQL'
        SELECT COUNT(*) AS total, SUM(eo.event_id IS NULL) AS gap
          FROM game_withdraw_order wo
          LEFT JOIN game_event_outbox eo
                 ON eo.event_id = CONCAT('withdraw_', wo.id, '_completed')
         WHERE wo.status = ? AND wo.paid_at >= NOW() - INTERVAL ? DAY
        SQL;

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

        // 提现完成事件对账（H2 追加约束「no-op 不允许静默」）：admin 侧未注册
        // EventPublisher 发布器时 withdraw.completed 永不进 outbox，本巡检把该缺口
        // 从看不见变成看得见（只读，不写业务表、不改生产者）
        Timer::add(self::GAP_INTERVAL, static fn () => self::reconcileWithdrawEvents());
    }

    /**
     * 提现完成事件对账（只读）：窗口内 status=completed 但 outbox 无
     * withdraw_{id}_completed 行的订单数——生产者在发事件时该行必然存在
     * （event_id 由 PayoutService::markCompleted 按同一格式写入，对应 uk_event_id 唯一键）。
     *
     *   total>0 且 gap>0 ⇒ 完成侧没有事件生产者（补上发布器后随窗口滚动归零）
     *   total>0 且 gap=0 ⇒ 生产者在工作
     *   total=0          ⇒ 窗口内无完成提现，结论未知（不报警，避免假绿灯）
     *
     * @return array{total:int,gap:int}|null 查询失败返回 null（不误报为 0）
     */
    public static function reconcileWithdrawEvents(): ?array
    {
        try {
            $row = Db::select(self::GAP_SQL, ['completed', self::GAP_WINDOW_DAYS])[0] ?? null;
        } catch (Throwable $e) {
            Log::error('Withdraw event reconciliation failed: ' . $e->getMessage());
            return null;
        }
        if ($row === null) {
            return null;
        }

        $total = (int) $row->total;
        $gap = (int) $row->gap;

        if ($gap > 0) {
            Log::warning('Withdraw event gap: completed orders without withdraw.completed outbox row', [
                'gap' => $gap,
                'window_total' => $total,
                'window_days' => self::GAP_WINDOW_DAYS,
            ]);
        }

        // 指标兜底，Redis 不可用时静默（日志已记录）
        try {
            Redis::setex(self::GAP_METRIC_KEY, 7200, (string) $gap);
        } catch (Throwable) {
        }

        return ['total' => $total, 'gap' => $gap];
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
