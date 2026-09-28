<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 可靠事件投递表（Outbox）。
 *
 * 关键资金事件与业务行同事务写入（EventBus::push()），
 * 由 outbox-consumer 进程轮询消费。
 */
class EventOutbox extends Model
{
    const STATUS_PENDING = 0; // 待消费
    const STATUS_SENT    = 1; // 已消费
    const STATUS_RETRY   = 2; // 预留值：当前全仓无写入方（消费失败的行保持 PENDING 不动，靠退避谓词重试）。
                              // 若将来要用 2，必须同时改消费端过滤：EventConsumer::drainBatch() 只取
                              // STATUS_PENDING，写 2 会让这些行既不重试、也不进死信，且没有任何日志。
    const STATUS_DEAD    = 3; // 死信，不再消费（人工介入）

    protected $table = 'event_outbox';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'event_id',
        'event',
        'payload',
        'status',
        'retry_count',
        'occurred_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => 'integer',
        'retry_count' => 'integer',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
