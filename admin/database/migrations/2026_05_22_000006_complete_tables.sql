-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 完整版数据表（7张）
-- ============================================================

-- ============================================================
-- 1. 游戏分类表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_category` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL COMMENT '分类名称',
    `slug` VARCHAR(50) NOT NULL COMMENT '分类标识',
    `icon` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '图标URL或Icon名',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏分类表';

-- ============================================================
-- 2. 游戏-分类关联表（多对多）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_category_rel` (
    `game_id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`game_id`, `category_id`),
    KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏-分类关联表';

-- ============================================================
-- 3. 排行榜定义表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_leaderboard` (
    `id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '游戏ID(0=全平台)',
    `name` VARCHAR(100) NOT NULL COMMENT '排行榜名称',
    `type` VARCHAR(20) NOT NULL DEFAULT 'total' COMMENT '类型: daily/weekly/monthly/total',
    `metric` VARCHAR(30) NOT NULL DEFAULT 'earned' COMMENT '排行指标: earned/spent/play_count/level',
    `rule` TEXT COMMENT '排行规则配置(JSON)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_game_type` (`game_id`, `type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='排行榜定义表';

-- ============================================================
-- 4. 优惠券表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_coupon` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT '优惠券名称',
    `type` VARCHAR(20) NOT NULL DEFAULT 'fixed' COMMENT '类型: fixed=固定金额 rate=比例折扣',
    `value` DECIMAL(18,4) NOT NULL COMMENT '优惠值(fixed=平台币 rate=折扣率如0.10=9折)',
    `min_amount` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '最低使用金额',
    `max_discount` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '最大优惠金额(rate类型)',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '适用游戏(0=全平台通用)',
    `total_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发行总量(0=不限)',
    `used_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取/使用数量',
    `user_limit` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '每人限领数量',
    `start_at` DATETIME DEFAULT NULL COMMENT '开始时间',
    `end_at` DATETIME DEFAULT NULL COMMENT '结束时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_at`, `end_at`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券表';

-- ============================================================
-- 5. 用户优惠券表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_user_coupon` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `coupon_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'unused' COMMENT 'unused/used/expired',
    `used_at` DATETIME DEFAULT NULL,
    `used_in_order` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '使用的订单号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_coupon_id` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券表';

-- ============================================================
-- 6. 国家差异化配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_country_config` (
    `id` BIGINT UNSIGNED NOT NULL,
    `country_code` VARCHAR(10) NOT NULL COMMENT 'ISO 3166-1 alpha-2',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '默认币种',
    `payment_methods` TEXT COMMENT '可用支付方式(JSON数组)',
    `withdraw_methods` TEXT COMMENT '可用提现方式(JSON数组)',
    `min_deposit` DECIMAL(18,4) NOT NULL DEFAULT 1.0000 COMMENT '最低充值额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_country_code` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家差异化配置表';

-- ============================================================
-- 7. 平台收益记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_platform_revenue` (
    `id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL COMMENT '收益日期',
    `source` VARCHAR(30) NOT NULL COMMENT '来源: exchange_spread/withdraw_fee/deposit_fee/game_share',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '游戏ID(0=非游戏来源)',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '收益金额(平台币)',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '统计币种',
    `count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '交易笔数',
    `remark` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_date_source` (`date`, `source`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台收益记录表';

-- ============================================================
-- 插入默认游戏分类
-- ============================================================
INSERT INTO `erik_game_category` (`id`, `name`, `slug`, `icon`, `sort`, `status`) VALUES
(50000000000000001, '动作', 'action', 'sports_kabaddi', 1, 1),
(50000000000000002, '冒险', 'adventure', 'explore', 2, 1),
(50000000000000003, '角色扮演', 'rpg', 'swords', 3, 1),
(50000000000000004, '策略', 'strategy', 'chess', 4, 1),
(50000000000000005, '休闲', 'casual', 'casino', 5, 1),
(50000000000000006, '竞技', 'competitive', 'emoji_events', 6, 1),
(50000000000000007, '模拟', 'simulation', 'flight', 7, 1),
(50000000000000008, '体育', 'sports', 'sports_soccer', 8, 1),
(50000000000000009, '益智', 'puzzle', 'extension', 9, 1),
(50000000000000010, '卡牌', 'card', 'style', 10, 1);

-- ============================================================
-- 插入默认国家配置
-- ============================================================
INSERT INTO `erik_country_config` (`id`, `country_code`, `currency`, `payment_methods`, `withdraw_methods`, `min_deposit`) VALUES
(50000000000000101, 'US', 'USD', '["stripe","paypal","crypto"]', '["paypal","bank","crypto"]', 1.0000),
(50000000000000102, 'CN', 'CNY', '["alipay","wechat"]', '["alipay","bank"]', 10.0000),
(50000000000000103, 'JP', 'JPY', '["stripe","paypal"]', '["paypal","bank"]', 100.0000),
(50000000000000104, 'KR', 'KRW', '["stripe","paypal"]', '["paypal","bank"]', 1000.0000),
(50000000000000105, 'GB', 'GBP', '["stripe","paypal"]', '["paypal","bank"]', 1.0000),
(50000000000000106, 'DE', 'EUR', '["stripe","paypal"]', '["paypal","bank"]', 1.0000),
(50000000000000107, 'BR', 'BRL', '["stripe"]', '["bank"]', 1.0000),
(50000000000000108, 'IN', 'INR', '["stripe"]', '["bank"]', 50.0000);
