<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

/**
 * 共享层唯一事件出口（H1 批次 0：类本体，默认 no-op，不注册）。
 * 宿主应用通过 setPublisher() 注册实际发布器（service 端由 H2 注册到 EventBus::push 走 Outbox）。
 * 三参带 $eventId —— 幂等的来源，对应 Outbox 表 uk_event_id 唯一键。
 */
class EventPublisher
{
    private static $publisher = null;

    /** 未注册时 no-op */
    public static function push(string $event, string $eventId, array $payload = []): void
    {
        if (self::$publisher === null) return;
        (self::$publisher)($event, $eventId, $payload);
    }

    public static function setPublisher(callable $publisher): void
    {
        self::$publisher = $publisher;
    }
}
