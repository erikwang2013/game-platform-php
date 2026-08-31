-- ============================================================
-- M6 风控可视化迁移：关联团伙表（人工确认结果落库）
-- 2026-08-31
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_risk_visual.sql
-- 说明: 聚类候选（同IP>=5账户/同设备>=3账户）由 /risk/clusters/detect 实时扫描得出，不落库；
--       本表仅存人工确认的团伙。纯 MySQL，兼容安装器迁移 runner。
-- ============================================================

CREATE TABLE IF NOT EXISTS `game_risk_cluster` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '团伙名称（人工命名）',
    `type` VARCHAR(30) NOT NULL COMMENT 'same_ip/same_device/same_pay_account/manual',
    `fingerprint` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '聚类依据值（ip_hash/fp_hash；manual 可空）',
    `member_ids` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '成员 user_id JSON 数组（无表可查的聚类类型用）',
    `user_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=观察中 2=已处置 0=误判',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='风控关联团伙表（人工确认）';
