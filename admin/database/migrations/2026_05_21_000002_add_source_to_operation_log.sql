-- ============================================================
-- 操作日志表增加操作来源端字段
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- ============================================================

ALTER TABLE `erik_operation_log`
ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '操作来源端: ipados|macos|windows|linux|ios|android|harmonyos|web' AFTER `ip`;

ALTER TABLE `erik_operation_log`
ADD KEY `idx_source` (`source`);
