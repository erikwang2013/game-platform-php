-- ============================================================
-- 可靠事件投递表（Outbox）
-- 关键资金事件（deposit/withdraw/exchange/risk）与业务行同事务写入，
-- 由 event-consumer 进程轮询消费；status=3 即死信，不另建 DLQ 表
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_event_outbox.sql
-- 与 install/install.sql 中 game_event_outbox DDL 保持一致（含 last_error 列）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_event_outbox` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `event_id` VARCHAR(64) NOT NULL COMMENT '业务幂等键，如 withdraw_123_completed，唯一',
    `event` VARCHAR(128) NOT NULL COMMENT '事件类型，如 withdraw.completed',
    `payload` JSON NOT NULL COMMENT '事件负载',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态: 0=pending 1=sent 3=dead；2 是预留值、当前全仓无写入方（消费端只取 pending，写 2 的行既不重试也不进死信且无日志）',
    `retry_count` INT NOT NULL DEFAULT 0 COMMENT '已尝试次数：每行被取到后先 +1 再消费（0=从未消费）；达到 3 即第 3 次尝试失败、转 dead（3 = EventConsumer::MAX_ATTEMPTS，改常量须同步本注释）',
    `last_error` VARCHAR(512) DEFAULT NULL COMMENT '最近一次错误信息（死信排查）',
    `occurred_at` DATETIME NOT NULL COMMENT '业务发生时间（排序与重放依据）',
    `processed_at` DATETIME DEFAULT NULL COMMENT '消费完成时间',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_event_id` (`event_id`),
    KEY `idx_status_occurred` (`status`, `occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='可靠事件投递表（Outbox）';
