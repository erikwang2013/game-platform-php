<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\api\v1\controller\WebhookController;
use app\event\EventBus;
use app\model\EventOutbox;
use app\service\AchievementService;
use app\service\ActivityService;
use app\service\AntiCheatService;
use support\Log;
use support\Redis;
use Workerman\Timer;

/**
 * Outbox 轮询消费进程：可靠投递（关键事件）的消费端。
 * 每 0.5s 拉取一批 pending 事件，逐条处理：
 *   成功 → status=1 (sent/consumed)
 *   失败 → retry_count+1，按有界指数退避（min(BASE << (n-1), BACKOFF_MAX) 秒）保持 pending 待重试，
 *          达到上限 → status=3 (dead)，同时落死信日志 + 累计计数（metrics:event_dead_total）
 * 非关键事件（game.played/referral.applied）由 EventSubscriber 进程走 Pub/Sub 消费。
 */
class EventConsumer
{
    private const MAX_ATTEMPTS = 3;
    /** 轮询间隔（秒） */
    private const POLL_INTERVAL = 0.5;
    /** 退避基数（秒）：第 n 次尝试前等待 min(BACKOFF_BASE << (n-1), BACKOFF_MAX) */
    private const BACKOFF_BASE = 30;
    /** 退避上界（秒）：有界，不随重试次数无限增长 */
    private const BACKOFF_MAX = 300;
    /** 一次 drain 抛异常（如库不可用）后的跳过窗口（秒），取代原来的 usleep */
    private const DRAIN_ERROR_BACKOFF = 2.0;
    /** 存量 gauge 刷新间隔（秒） */
    private const GAUGE_INTERVAL = 60;
    /** 死信累计计数：只增不减，供 Prometheus/告警消费 */
    private const METRIC_DEAD_TOTAL = 'metrics:event_dead_total';
    /** 存量 gauge：pending / dead 行数，无 API，运维从这里读死信规模 */
    private const GAUGE_PENDING = 'health:event_outbox_pending';
    private const GAUGE_DEAD = 'health:event_outbox_dead';

    /**
     * 退避谓词（有界指数退避）：第 n 次尝试前等 min(BACKOFF_BASE << (n-1), BACKOFF_MAX) 秒。
     * 与 backoffSeconds() 同一公式，改一处必须同步另一处。
     * 「上次尝试时间」用 updated_at —— Eloquent 每次 save()（含失败路径的 retry_count+1）自动刷新，
     * 无需新增列（表上也没有 next_attempt_at）。退避判定放 SQL 而不是取回后在 PHP 里跳过：
     * 否则未到期的行会长期占住 order by occurred_at + limit 50 的批次头部，把后面已到期的行饿死。
     * 位运算而非 POW：整数运算，不引入浮点。
     * public 是为了让无库断言能钉住退避公式（与 Health::GAP_SQL 同例）。
     */
    public const BACKOFF_PREDICATE = '(retry_count = 0 OR TIMESTAMPDIFF(SECOND, COALESCE(updated_at, created_at), NOW()) >= LEAST('
        . self::BACKOFF_BASE . ' << GREATEST(retry_count - 1, 0), ' . self::BACKOFF_MAX . '))';

    /** 一次 drain 抛异常后的恢复时刻（不阻塞事件循环的退避） */
    private static float $resumeAt = 0.0;

    public function onWorkerStart(): void
    {
        Log::info('EventConsumer started (outbox polling)');

        // 定时器而非 while(true)+sleep：onWorkerStart 必须尽快返回。workerman 的 Worker::run()
        // 是在 onWorkerStart 返回【之后】才进 $globalEvent->run()，而子进程的信号处理器由
        // reinstallSignal() 挂在事件循环上 ⇒ 阻塞在 onWorkerStart 里信号永不派发，
        // graceful stop（SIGINT/SIGQUIT）失效、只能 SIGKILL；SIGKILL 落在批中途会让
        // 已派发未落 status=1 的行重放（重复投递）。
        Timer::add(self::POLL_INTERVAL, static function (): void {
            if (microtime(true) < self::$resumeAt) {
                return;   // drain 刚抛过异常，退避窗口内不重试（原为 usleep(2s)，此处不阻塞循环）
            }
            try {
                self::drainBatch();
                self::$resumeAt = 0.0;
            } catch (\Throwable $e) {
                Log::error('EventConsumer drain failed: ' . $e->getMessage());
                self::$resumeAt = microtime(true) + self::DRAIN_ERROR_BACKOFF;
            }
        });

        Timer::add(self::GAUGE_INTERVAL, static fn () => self::publishBacklogGauges());
    }

    /**
     * 按 occurred_at 拉取一批待消费事件，逐条处理（断点续传 + 批内顺序）。
     * 单进程消费（process.php count=1），lockForUpdate 为并发扩容预留。
     */
    private static function drainBatch(): void
    {
        $rows = EventOutbox::where('status', EventOutbox::STATUS_PENDING)
            ->where('retry_count', '<', self::MAX_ATTEMPTS)
            ->whereRaw(self::BACKOFF_PREDICATE)
            ->orderBy('occurred_at')
            ->limit(50)
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $row->retry_count = $row->retry_count + 1;
            $row->save();

            try {
                self::dispatch((string) $row->event, $row->payload, (string) $row->event_id);

                $row->status = EventOutbox::STATUS_SENT;
                $row->processed_at = date('Y-m-d H:i:s');
                $row->last_error = '';
                $row->save();
            } catch (\Throwable $e) {
                $row->last_error = mb_substr($e->getMessage(), 0, 512);
                $dead = $row->retry_count >= self::MAX_ATTEMPTS;
                if ($dead) {
                    $row->status = EventOutbox::STATUS_DEAD;
                }
                $row->save();

                if ($dead) {
                    self::markDeadLetter($row);
                } else {
                    Log::error('EventOutbox consume failed, will retry', [
                        'event_id'      => $row->event_id,
                        'event'         => $row->event,
                        'retry_count'   => $row->retry_count,
                        'next_retry_in' => self::backoffSeconds((int) $row->retry_count),
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 有界指数退避：第 $attempts 次尝试【之前】需等待的秒数（0 = 首投，不等待）。
     * 与 BACKOFF_PREDICATE 同一公式（同样的位移 + LEAST 上界），改一处必须同步另一处。
     */
    public static function backoffSeconds(int $attempts): int
    {
        if ($attempts <= 0) {
            return 0;
        }
        // 位移封顶 16 位：30 << 16 ≈ 1.97e6 秒已远超上界 BACKOFF_MAX，取 min 后恒等于上界；
        // 不封顶则 $attempts 极大时 30 << n 在 PHP 整数位移下溢出成负数，退避反而变成负等待。
        return (int) min(self::BACKOFF_BASE << min($attempts - 1, 16), self::BACKOFF_MAX);
    }

    /**
     * 死信可见性：一次留下两类痕迹 —— 结构化错误日志（人排查）+ 累计计数（告警消费）。
     * 死信表内就地留存（status=3 + last_error），不另建 DLQ 表；重放靠人工改回 pending。
     * Redis 不可用时静默（日志已留）。
     */
    private static function markDeadLetter(EventOutbox $row): void
    {
        Log::error('EventOutbox dead-lettered: 重试 ' . self::MAX_ATTEMPTS . ' 次仍失败，需人工介入重放', [
            'event_id'    => $row->event_id,
            'event'       => $row->event,
            'retry_count' => $row->retry_count,
            'last_error'  => $row->last_error,
        ]);
        try {
            Redis::incr(self::METRIC_DEAD_TOTAL);
        } catch (\Throwable) {
        }
    }

    /**
     * 存量 gauge：pending / dead 行数，供告警与人工核查（本进程不提供 API）。
     * TTL 取 3 倍刷新间隔：消费进程死掉时 gauge 会自然过期，而不是留一个说"没问题"的旧值。
     */
    private static function publishBacklogGauges(): void
    {
        try {
            Redis::setex(
                self::GAUGE_PENDING,
                self::GAUGE_INTERVAL * 3,
                (string) EventOutbox::where('status', EventOutbox::STATUS_PENDING)->count()
            );
            Redis::setex(
                self::GAUGE_DEAD,
                self::GAUGE_INTERVAL * 3,
                (string) EventOutbox::where('status', EventOutbox::STATUS_DEAD)->count()
            );
        } catch (\Throwable) {
        }
    }

    /**
     * 统一派发：Outbox 路径与 Pub/Sub 路径（EventSubscriber）共用。
     * 关键事件的下游异常向上抛，驱动 Outbox 重试直至死信；
     * 非关键事件异常仅记日志，不影响主流程。
     */
    public static function dispatch(string $event, array $payload, ?string $eventId = null): void
    {
        $ctx = ['event' => $event, 'event_id' => $eventId, 'timestamp' => time()];
        $failures = [];

        try {
            AchievementService::handle($event, $payload);
        } catch (\Throwable $e) {
            Log::warning('EventConsumer achievement failed: ' . $e->getMessage(), $ctx);
            $failures[] = $e;
        }

        try {
            WebhookController::dispatch($event, $payload, $eventId);
        } catch (\Throwable $e) {
            Log::error('EventConsumer webhook failed: ' . $e->getMessage(), $ctx);
            $failures[] = $e;
        }

        // 反作弊旁路：异常隔离不阻塞主链路，也不进 $failures（非可靠事件，不驱动 Outbox 重试）
        try {
            if ($event === AntiCheatService::EVENT_ROUND_FINISHED) {
                AntiCheatService::onRoundFinished($payload);
            }
        } catch (\Throwable $e) {
            Log::warning('EventConsumer anticheat failed: ' . $e->getMessage(), $ctx);
        }

        // 活动引擎旁路：语义与成就同档——业务性跳过（活动结束/事件不匹配）在 handler 内吞掉，
        // 系统异常进 $failures 驱动可靠事件重试（进度累加同事务回滚，重放不双算）
        try {
            ActivityService::handle($event, $payload);
        } catch (\Throwable $e) {
            Log::warning('EventConsumer activity failed: ' . $e->getMessage(), $ctx);
            $failures[] = $e;
        }

        if ($failures !== [] && in_array($event, EventBus::RELIABLE_EVENTS, true)) {
            throw $failures[0];
        }
    }
}
