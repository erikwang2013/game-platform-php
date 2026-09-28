<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\SnowflakeService;
use Illuminate\Database\Eloquent\Casts\Json;
use support\Db;

/**
 * Outbox 写入的唯一致信实现（service / admin 两棵树共用）。
 *
 * 为什么放共享层：service 侧的发出口是 app\event\EventBus::push()，admin 侧只能经
 * EventPublisher 的注册缝发布（admin 既没有 app\model\EventOutbox，也没有 EventBus::push）。
 * 两边各写一份 insert 迟早漂移出「一边写得进、另一边读不到」的静默故障——本仓已有同形前例
 * （Encryptable 的 cipher 在两树分歧，admin 读 service 写的=500、反向=401）。
 *
 * 事务语义（硬约束，见 EventBus::push 文档）：调用方已在事务内 ⇒ 并入当前事务，
 * 业务行与事件行同提交；调用方不在事务内 ⇒ 自包一层。绝不吞异常：写不进去必须让调用方
 * 的资金事务回滚（失败即回滚，好过「钱动了、事件没发」）。
 */
class OutboxWriter
{
    /** Outbox 表名（`game_` 前缀由连接配置处理） */
    private const TABLE = 'event_outbox';

    /**
     * 待消费状态。与 app\model\EventOutbox::STATUS_PENDING 同值——共享层不能引用任一树的
     * app\model，故此处重声明；表列注释（install/migrations/2026_08_31_event_outbox.sql）
     * 与本常量共同定义该值，改一处必须同步另一处。
     */
    public const STATUS_PENDING = 0;

    public static function write(string $event, string $eventId, array $payload): void
    {
        if (Db::transactionLevel() > 0) {
            self::insert($event, $eventId, $payload);
            return;
        }

        Db::transaction(static fn () => self::insert($event, $eventId, $payload));
    }

    /**
     * 单行插入。列集合与 app\model\EventOutbox 的 Eloquent 写法逐列等价
     * （retry_count / last_error / processed_at 由表默认值填充，模型也不设这三列）。
     * payload 走 Eloquent 的同一编码器（array cast → Casts\Json::encode），
     * 以保证两棵树写入的 JSON 字节一致、且尊重 Json::encodeUsing() 的自定义编码器。
     */
    private static function insert(string $event, string $eventId, array $payload): void
    {
        $now = date('Y-m-d H:i:s');

        Db::table(self::TABLE)->insert([
            'id'          => SnowflakeService::generate(),
            'event_id'    => $eventId,               // 幂等键，uk_event_id 唯一
            'event'       => $event,
            'payload'     => Json::encode($payload),
            'status'      => self::STATUS_PENDING,
            'occurred_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }
}
