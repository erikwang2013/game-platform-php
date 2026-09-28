<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\bootstrap;

use common\service\EventPublisher;
use common\service\OutboxWriter;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * admin 端把共享层的事件出口接到真出口（Outbox）。
 *
 * 不注册时 EventPublisher::push() 是 no-op（EventPublisher.php:22 直接 return），
 * 而 admin 是 PayoutService::markCompleted() 的唯一运行处 —— 于是 withdraw.completed
 * 一直「调用成功但什么都没发生」，Health::GAP_SQL 的对账缺口永不闭合。
 * admin 树没有 app\model\EventOutbox、EventBus 也没有 push，故接到共享层的 OutboxWriter，
 * 与 service 侧 EventBus::push() 是同一条写入实现（列集合与 JSON 编码不会两树漂移）。
 */
class EventPublisherBootstrap implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        EventPublisher::setPublisher(static function (string $event, string $eventId, array $payload): void {
            OutboxWriter::write($event, $eventId, $payload);
        });
    }
}
