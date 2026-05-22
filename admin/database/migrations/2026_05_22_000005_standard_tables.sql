-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 标准版数据表（10张）
-- ============================================================

-- ============================================================
-- 1. 第三方登录关联表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_oauth` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `provider` VARCHAR(20) NOT NULL COMMENT '平台: google/facebook/apple',
    `open_id` VARCHAR(255) NOT NULL COMMENT '第三方用户唯一标识',
    `union_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '跨应用统一ID',
    `access_token` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方access_token(加密)',
    `refresh_token` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方refresh_token(加密)',
    `token_expires_at` DATETIME DEFAULT NULL,
    `raw_data` TEXT COMMENT '第三方返回的原始用户数据(JSON)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_provider_openid` (`provider`, `open_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方登录关联表';

-- ============================================================
-- 2. 用户登录会话表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_session` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_id` VARCHAR(64) NOT NULL COMMENT 'JWT jti',
    `device` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '设备: web/ios/android/harmonyos',
    `ip` VARCHAR(50) NOT NULL DEFAULT '',
    `location` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'IP归属地',
    `user_agent` VARCHAR(500) NOT NULL DEFAULT '',
    `logged_in_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expired_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token_id` (`token_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expired_at` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户登录会话表';

-- ============================================================
-- 3. 实名认证表 (KYC)
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_identity` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `real_name` VARCHAR(500) NOT NULL COMMENT '真实姓名(加密)',
    `id_type` VARCHAR(20) NOT NULL DEFAULT 'id_card' COMMENT '证件类型: id_card/passport/driver_license',
    `id_number` VARCHAR(500) NOT NULL COMMENT '证件号码(加密)',
    `id_front_photo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '证件正面照片URL',
    `id_back_photo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '证件背面照片URL',
    `selfie_photo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '手持证件照URL',
    `country` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '签发国家',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected',
    `reviewer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人ID',
    `review_note` VARCHAR(500) NOT NULL DEFAULT '',
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='实名认证表';

-- ============================================================
-- 4. 用户收款账户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_payment_account` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT '类型: paypal/alipay/bank/crypto_wallet',
    `account_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '账户名',
    `account_info` VARCHAR(500) NOT NULL COMMENT '账户详情(加密): PayPal邮箱/银行账号/钱包地址',
    `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认账户',
    `is_verified` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已验证',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户收款账户表';

-- ============================================================
-- 5. 提现限额规则表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_withdraw_limit` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_level` VARCHAR(20) NOT NULL DEFAULT 'default' COMMENT '用户等级: default/verified/vip/svip',
    `single_min` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 1.0000 COMMENT '单笔最低',
    `single_max` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 1000.0000 COMMENT '单笔最高',
    `daily_limit` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 10000.0000 COMMENT '日限额',
    `monthly_limit` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 50000.0000 COMMENT '月限额',
    `fee_pct` DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.50 COMMENT '手续费率%',
    `fee_max` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 50.0000 COMMENT '手续费上限',
    `auto_approve_threshold` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 100.0000 COMMENT '自动审核阈值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level` (`user_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现限额规则表';

-- ============================================================
-- 6. 游戏区服表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_server` (
    `id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL COMMENT '区服名称',
    `region` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '区域: global/asia/eu/na',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=维护 1=正常 2=火爆 3=新服',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_game_id` (`game_id`),
    KEY `idx_region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏区服表';

-- ============================================================
-- 7. 游戏记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_play_log` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `server_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '游戏会话ID',
    `action` VARCHAR(20) NOT NULL COMMENT 'start/end/earn/spend',
    `game_amount_before` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `game_amount_change` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '变动(正=赚,负=花)',
    `game_amount_after` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `platform_amount_change` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '平台币等值变动',
    `metadata` TEXT COMMENT '游戏自定义数据(JSON)',
    `started_at` DATETIME DEFAULT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_game` (`user_id`, `game_id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏记录表';

-- ============================================================
-- 8. 风控规则表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_risk_rule` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT '规则名称',
    `type` VARCHAR(30) NOT NULL COMMENT '类型: ip_blacklist/amount_anomaly/frequency/velocity/device_fingerprint',
    `config` TEXT NOT NULL COMMENT '规则配置(JSON): 阈值/时间窗口/动作',
    `action` VARCHAR(20) NOT NULL DEFAULT 'log' COMMENT '触发动作: log=记录/warn=警告/block=阻断',
    `priority` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '优先级，越大越先执行',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_priority` (`status`, `priority` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='风控规则表';

-- ============================================================
-- 9. 风控日志表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_risk_log` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `rule_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `type` VARCHAR(30) NOT NULL COMMENT '匹配的风控类型',
    `action` VARCHAR(20) NOT NULL COMMENT '执行动作: log/warn/block',
    `context` TEXT COMMENT '触发上下文(JSON): IP/设备/金额/频率等',
    `result` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '处理结果: passed/blocked/manual_review',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='风控日志表';

-- ============================================================
-- 10. 日统计快照表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_stat_daily` (
    `id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL COMMENT '统计日期',
    `stat_type` VARCHAR(30) NOT NULL COMMENT '类型: revenue/users/game/deposit/withdraw/exchange',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '游戏ID(0=全平台)',
    `metrics` TEXT NOT NULL COMMENT '指标数据(JSON): {new_users, active_users, total_amount, count...}',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_date_type_game` (`date`, `stat_type`, `game_id`),
    KEY `idx_date` (`date`),
    KEY `idx_type` (`stat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日统计快照表';

-- ============================================================
-- 插入默认风控规则
-- ============================================================
INSERT INTO `erik_risk_rule` (`id`, `name`, `type`, `config`, `action`, `priority`, `status`) VALUES
(40000000000000001, 'IP黑名单检测', 'ip_blacklist', '{"blacklist":[]}', 'block', 100, 1),
(40000000000000002, '单笔大额充值预警', 'amount_anomaly', '{"min_amount":"5000","currency":"USD"}', 'warn', 50, 1),
(40000000000000003, '高频提现检测', 'frequency', '{"window_minutes":60,"max_count":5}', 'warn', 50, 1),
(40000000000000004, '短时多账号检测', 'velocity', '{"window_minutes":10,"max_accounts":3,"same_ip":true}', 'block', 80, 1);

-- ============================================================
-- 插入默认提现限额规则
-- ============================================================
INSERT INTO `erik_withdraw_limit` (`id`, `user_level`, `single_min`, `single_max`, `daily_limit`, `monthly_limit`, `fee_pct`, `fee_max`, `auto_approve_threshold`) VALUES
(40000000000000010, 'default', 1.0000, 1000.0000, 10000.0000, 50000.0000, 1.00, 50.0000, 100.0000),
(40000000000000011, 'verified', 1.0000, 5000.0000, 50000.0000, 200000.0000, 0.50, 25.0000, 500.0000),
(40000000000000012, 'vip', 1.0000, 20000.0000, 200000.0000, 1000000.0000, 0.00, 0.0000, 5000.0000);
