-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 游戏聚合平台基础版核心数据表
-- 版本: 基础版 (MVP)
-- 注意: 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- 1. C端用户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt哈希）',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像URL',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `country` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '国家代码，ISO 3166-1 alpha-2',
    `language` VARCHAR(10) NOT NULL DEFAULT 'en-US' COMMENT '语言偏好',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_country` (`country`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='C端用户表';

-- ============================================================
-- 2. 平台币钱包表（含乐观锁）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_wallet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '可用余额（平台币）',
    `frozen_balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '冻结余额（提现中）',
    `total_earned` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '累计收入（平台币）',
    `total_spent` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '累计支出（平台币）',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁版本号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台币钱包表';

-- ============================================================
-- 3. 游戏币钱包表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_game_wallet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `currency_id` BIGINT UNSIGNED NOT NULL COMMENT '币种ID',
    `balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '游戏币余额',
    `frozen_balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '冻结游戏币余额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_game_currency` (`user_id`, `game_id`, `currency_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏币钱包表';

-- ============================================================
-- 4. 游戏表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '游戏名称',
    `slug` VARCHAR(50) NOT NULL COMMENT '游戏标识',
    `type` VARCHAR(20) NOT NULL DEFAULT 'third_party' COMMENT '游戏类型: self=自研 third_party=第三方',
    `description` TEXT COMMENT '游戏简介',
    `cover_image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '封面图',
    `api_endpoint` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '第三方游戏API地址',
    `api_key` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方API密钥（加密存储）',
    `api_secret` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方API密钥（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=下架 1=上架',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏表';

-- ============================================================
-- 5. 游戏币种表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_currency` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `name` VARCHAR(50) NOT NULL COMMENT '币种名称',
    `symbol` VARCHAR(20) NOT NULL COMMENT '币种符号',
    `exchange_rate` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 COMMENT '兑换率（1平台币 = X游戏币）',
    `spread_pct` DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT '平台抽成百分比',
    `min_exchange` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '最小兑换数量（平台币）',
    `max_exchange` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 999999.9999 COMMENT '最大兑换数量（平台币）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏币种表';

-- ============================================================
-- 6. 充值订单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_deposit_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '充值金额（法币）',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '法币币种（USD/CNY/EUR等）',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '到账平台币数量',
    `payment_method_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '支付方式ID',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待支付 paid=已支付 confirmed=已确认 cancelled=已取消',
    `transaction_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '第三方支付交易ID',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单表';

-- ============================================================
-- 7. 提现订单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_withdraw_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '提现平台币数量',
    `fiat_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '到账法币金额',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '提现法币币种',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '提现方式: paypal/bank/crypto',
    `account_info` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收款账户信息（加密存储）',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待审核 approved=已通过 rejected=已拒绝 completed=已完成',
    `reviewer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人ID（关联erik_admin_user）',
    `review_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '审核附注',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现订单表';

-- ============================================================
-- 8. 兑换记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_exchange_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `currency_id` BIGINT UNSIGNED NOT NULL COMMENT '币种ID',
    `direction` VARCHAR(4) NOT NULL COMMENT '方向: in=平台币→游戏币 out=游戏币→平台币',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '平台币数量',
    `game_amount` DECIMAL(18,4) NOT NULL COMMENT '游戏币数量',
    `rate` DECIMAL(18,8) NOT NULL COMMENT '成交汇率',
    `spread_fee` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '平台手续费（平台币）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '兑换时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_game_id` (`game_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='兑换记录表';

-- ============================================================
-- 9. 平台流水表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_transaction` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '流水类型: deposit/withdraw/exchange_in/exchange_out/game_earn/game_spend',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '变动金额（正=收入，负=支出）',
    `balance_after` DECIMAL(18,4) NOT NULL COMMENT '变动后余额',
    `ref_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关联单据类型',
    `ref_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联单据ID',
    `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '流水时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id_type` (`user_id`, `type`),
    KEY `idx_ref` (`ref_type`, `ref_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台流水表';

-- ============================================================
-- 10. 支付方式表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_payment_method` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '支付方式名称',
    `type` VARCHAR(20) NOT NULL COMMENT '类型: fiat=法币 crypto=加密货币',
    `provider` VARCHAR(50) NOT NULL COMMENT '提供商: stripe/paypal/alipay/wechat',
    `config` TEXT COMMENT '支付配置（加密JSON）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=禁用 1=启用',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付方式表';

-- ============================================================
-- 11. 公告表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_announcement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `title` VARCHAR(255) NOT NULL COMMENT '标题',
    `content` TEXT NOT NULL COMMENT '内容',
    `type` VARCHAR(20) NOT NULL DEFAULT 'system' COMMENT '公告类型: system=系统公告 game=游戏公告 payment=支付公告',
    `target_lang` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '目标语言（空=全语言）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发布',
    `start_at` DATETIME DEFAULT NULL COMMENT '开始展示时间',
    `end_at` DATETIME DEFAULT NULL COMMENT '结束展示时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_at`, `end_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告表';

-- ============================================================
-- 12. 平台配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_platform_config` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `group` VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT '配置分组: withdraw/payment/game/system',
    `key` VARCHAR(100) NOT NULL COMMENT '配置键名',
    `value` TEXT COMMENT '配置值',
    `type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型: string|int|bool|json|decimal',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置项说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台配置表';

-- ============================================================
-- 插入默认配置
-- ============================================================
INSERT INTO `erik_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20000000000000001, 'withdraw', 'global_switch', '1', 'bool', '全局提现开关: 1=允许提现 0=禁止提现'),
(20000000000000002, 'withdraw', 'auto_approve_threshold', '100.0000', 'decimal', '自动审核阈值（平台币），低于此金额自动通过'),
(20000000000000003, 'withdraw', 'daily_limit', '10000.0000', 'decimal', '每人每日提现上限（平台币）'),
(20000000000000004, 'withdraw', 'min_amount', '1.0000', 'decimal', '单笔最低提现金额（平台币）'),
(20000000000000005, 'payment', 'default_exchange_rate', '1.00000000', 'decimal', '默认平台币兑USD汇率'),
(20000000000000006, 'system', 'site_name', 'Global Game Platform', 'string', '平台名称');
