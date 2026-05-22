-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 生产级功能表（通知/推荐/2FA）
-- ============================================================

-- ============================================================
-- 1. 通知表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_notification` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID(0=全站通知)',
    `type` VARCHAR(30) NOT NULL DEFAULT 'system' COMMENT '类型: system/deposit/withdraw/kyc/coupon/announcement',
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ref_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关联单据类型',
    `ref_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联单据ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知表';

-- ============================================================
-- 2. 推荐关系表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_referral` (
    `id` BIGINT UNSIGNED NOT NULL,
    `referrer_id` BIGINT UNSIGNED NOT NULL COMMENT '推荐人用户ID',
    `referred_id` BIGINT UNSIGNED NOT NULL COMMENT '被推荐人用户ID',
    `code` VARCHAR(20) NOT NULL COMMENT '推荐码',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=无效 1=有效',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_referred_id` (`referred_id`),
    KEY `idx_referrer_id` (`referrer_id`),
    KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推荐关系表';

-- ============================================================
-- 3. 推荐奖励表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_referral_reward` (
    `id` BIGINT UNSIGNED NOT NULL,
    `referral_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '获得奖励的用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '类型: signup=注册奖励 deposit=充值返佣',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '奖励金额(平台币)',
    `source_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '触发奖励的原始金额',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/credited/cancelled',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_referral_id` (`referral_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推荐奖励表';

-- ============================================================
-- 4. 2FA 双因素认证表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_2fa` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `secret` VARCHAR(500) NOT NULL COMMENT 'TOTP密钥(加密)',
    `is_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=未启用 1=已启用',
    `backup_codes` TEXT COMMENT '备用恢复码(JSON数组,加密)',
    `enabled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2FA双因素认证表';

-- ============================================================
-- 插入推荐配置
-- ============================================================
INSERT INTO `erik_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(60000000000000001, 'referral', 'signup_reward', '5.0000', 'decimal', '注册奖励(平台币)，推荐人和被推荐人各得'),
(60000000000000002, 'referral', 'deposit_commission_pct', '5.00', 'decimal', '充值返佣比例(%)');
