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
use support\Log;

/**
 * Outbox 轮询消费进程：可靠投递（关键事件）的消费端。
 * 每 0.5s 拉取一批 pending 事件，逐条处理：
 *   成功 → status=1 (sent/consumed)
 *   失败 → retry_count+1，不足上限保持 pending 下批重试，达到上限 → status=3 (dead)
 * 非关键事件（game.played/referral.applied）由 EventSubscriber 进程走 Pub/Sub 消费。
 */
class EventConsumer
{
    private const MAX_ATTEMPTS = 3;

    public function onWorkerStart(): void
    {
        Log::info('EventConsumer started (outbox polling)');

        while (true) {
            try {
                self::drainBatch();
            } catch (\Throwable $e) {
                Log::error('EventConsumer drain failed: ' . $e->getMessage());
                usleep(2_000_000);
            }
            usleep(500_000);   // 0.5s 轮询间隔
        }
    }

    /**
     * 按 occurred_at 拉取一批待消费事件，逐条处理（断点续传 + 批内顺序）。
     * 单进程消费（process.php count=1），lockForUpdate 为并发扩容预留。
     */
    private static function drainBatch(): void
    {
        $rows = EventOutbox::where('status', EventOutbox::STATUS_PENDING)
            ->where('retry_count', '<', self::MAX_ATTEMPTS)
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
                if ($row->retry_count >= self::MAX_ATTEMPTS) {
                    $row->status = EventOutbox::STATUS_DEAD;
                }
                $row->save();
                Log::error('EventOutbox consume failed', [
                    'event_id' => $row->event_id,
                    'event' => $row->event,
                    'retry_count' => $row->retry_count,
                    'error' => $e->getMessage(),
                ]);
            }
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

        if ($failures !== [] && in_array($event, EventBus::RELIABLE_EVENTS, true)) {
            throw $failures[0];
        }
    }
}
