-- ============================================================
-- 对账/结算模块迁移：3 张对账表
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_reconciliation.sql
-- ============================================================

-- 1. 对账批次表
CREATE TABLE IF NOT EXISTS `game_reconciliation_batch` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(128) NOT NULL COMMENT '批次名称',
    `gateway` VARCHAR(64) NOT NULL COMMENT '网关名',
    `date_range_start` DATE NOT NULL COMMENT '对账日期范围开始',
    `date_range_end` DATE NOT NULL COMMENT '对账日期范围结束',
    `status` ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待执行 running=执行中 completed=已完成 failed=失败',
    `error_msg` VARCHAR(512) DEFAULT NULL COMMENT '失败原因',
    `total_statements` INT NOT NULL DEFAULT 0 COMMENT '网关明细笔数',
    `matched_count` INT NOT NULL DEFAULT 0 COMMENT '匹配成功笔数',
    `diff_count` INT NOT NULL DEFAULT 0 COMMENT '差异笔数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_gateway_date` (`gateway`, `date_range_start`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账批次表';

-- 2. 对账单明细表（网关侧原始明细，人工CSV上传后无法重新拉取，必须留底）
CREATE TABLE IF NOT EXISTS `game_reconciliation_statement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `batch_id` BIGINT UNSIGNED NOT NULL COMMENT '批次ID',
    `gateway` VARCHAR(64) NOT NULL COMMENT '网关名',
    `external_id` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '网关侧交易ID（法币=流水号，crypto=txHash）',
    `amount` DECIMAL(20,4) NOT NULL DEFAULT 0.0000 COMMENT '网关结算金额',
    `currency` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '网关币种',
    `status` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '网关侧状态',
    `transaction_time` DATETIME DEFAULT NULL COMMENT '网关交易时间',
    `local_order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '匹配的本地订单ID（NULL=未匹配）',
    `matched` TINYINT NOT NULL DEFAULT 0 COMMENT '是否匹配: 0=否 1=是',
    `raw` JSON DEFAULT NULL COMMENT '原始对账数据',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_batch_id` (`batch_id`),
    KEY `idx_external_id` (`external_id`),
    KEY `idx_local_order_id` (`local_order_id`),
    KEY `idx_matched` (`matched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账单明细表';

-- 3. 对账差异表（只落差异，不落匹配成功——健康系统下匹配成功占 99%+）
CREATE TABLE IF NOT EXISTS `game_reconciliation_diff` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `batch_id` BIGINT UNSIGNED NOT NULL COMMENT '批次ID',
    `statement_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对账单明细ID（0=无）',
    `local_order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '本地订单ID（NULL=无）',
    `diff_type` VARCHAR(32) NOT NULL COMMENT '差异类型: amount_mismatch/status_mismatch/missing_local/missing_gateway/duplicate_deposit/payout_unconfirmed/time_only/currency_mismatch',
    `severity` VARCHAR(16) NOT NULL DEFAULT 'medium' COMMENT '严重度: low/medium/high/critical',
    `description` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '差异描述',
    `resolution` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '处理状态: pending/resolved/ignored',
    `resolved_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '处理人ID（0=系统）',
    `resolved_at` DATETIME DEFAULT NULL COMMENT '处理时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_batch_id` (`batch_id`),
    KEY `idx_statement_id` (`statement_id`),
    KEY `idx_local_order_id` (`local_order_id`),
    KEY `idx_diff_type` (`diff_type`),
    KEY `idx_resolution` (`resolution`),
    KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账差异表';
