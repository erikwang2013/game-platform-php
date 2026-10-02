<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\event\EventBus;
use support\Log;

/**
 * Redis Pub/Sub 消费进程：承接非关键事件（game.played/referral.applied 等）。
 * 与 Outbox 轮询进程（EventConsumer）共用静态 dispatch，派发逻辑不重复实现。
 */
class EventSubscriber
{
    public function onWorkerStart(): void
    {
        Log::info('EventSubscriber started', ['channel' => EventBus::CHANNEL]);

        while (true) {
            try {
                EventBus::subscribe(function (string $message): void {
                    $this->dispatchMessage($message);
                });
            } catch (\Throwable $e) {
                Log::warning('EventSubscriber subscribe interrupted, reconnecting: ' . $e->getMessage());
                sleep(3);
            }
        }
    }

    private function dispatchMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            if (!is_array($data) || empty($data['event'])) {
                Log::warning('EventSubscriber invalid message', [
                    'raw' => substr($message, 0, 200),
                ]);
                return;
            }

            $event = (string) $data['event'];
            $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
            // id 由发布者（EventBus::emit）生成，本进程只透传，绝不在此另生成一个：
            // 每个订阅者各生成一个 id 会让同一个事件在不同消费者眼里是不同的东西。
            // 老格式消息无 id ⇒ null；非标量（数组/对象）同样落 null，避免 (string) 转型告警。
            // 两条路的 id【不同源】，别把它当成同一个东西：outbox 路径传的是行上的业务键
            // （drainBatch 的 (string) $row->event_id，在 game_event_outbox 里有行），本路径传的
            // 是 emit() 的 snowflake（outbox 里永远没有对应行）。WebhookController::dispatch 的
            // 幂等去重（where event_id + status=SENT exists）因此在 outbox 路径可能命中、
            // 在本路径恒为 false —— pub/sub 路径的去重【从未触发】，仍是待办；
            // 下一批做它时不能照抄那个查询。（本次注释修正不改变任何运行时行为。）
            $eventId = is_scalar($data['id'] ?? null) ? (string) $data['id'] : null;

            EventConsumer::dispatch($event, $payload, $eventId);
        } catch (\Throwable $e) {
            Log::warning('EventSubscriber dispatch failed: ' . $e->getMessage());
        }
    }
}
