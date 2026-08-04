-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 一键安装 SQL（合并所有迁移文件）
-- 包含: 管理后台 + 平台业务 + 国际化 + 标准版 + 完整版 + 生产级
-- 注意: 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

START TRANSACTION;

-- ============================================================
-- 管理用户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_admin_user` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt哈希）',
    `real_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
    `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像URL',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `id_card` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '身份证号（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理用户表';

-- ============================================================
-- 角色表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_admin_role` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
    `slug` VARCHAR(50) NOT NULL COMMENT '角色标识，用于权限判断',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '角色描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

-- ============================================================
-- 权限表（菜单/按钮/接口）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_admin_permission` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级权限ID，0表示顶级',
    `name` VARCHAR(50) NOT NULL COMMENT '权限名称',
    `slug` VARCHAR(100) NOT NULL COMMENT '权限标识，格式: 模块.操作（如 user.create）',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '类型: 1=菜单 2=按钮 3=API接口',
    `icon` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '菜单图标（仅type=1时使用）',
    `path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '前端路由路径（仅type=1时使用）',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限表';

-- ============================================================
-- 用户角色关联表（多对多中间表）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_admin_user_role` (
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';

-- ============================================================
-- 角色权限关联表（多对多中间表）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_admin_role_permission` (
    `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限ID',
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- ============================================================
-- 系统配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_system_config` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `group` VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT '配置分组标识',
    `key` VARCHAR(100) NOT NULL COMMENT '配置键名',
    `value` TEXT COMMENT '配置值',
    `type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型: string|int|bool|json|array',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置项说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================================
-- 操作日志表（含 source 来源端字段）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_operation_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
    `action` VARCHAR(100) NOT NULL COMMENT '操作动作，如 admin.user.store',
    `method` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '请求方法: GET|POST|PUT|DELETE',
    `path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '请求路径',
    `ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作IP',
    `source` VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '操作来源端: ipados|macos|windows|linux|ios|android|harmonyos|web',
    `input` TEXT COMMENT '请求参数（敏感字段已脱敏）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ============================================================
-- C端用户表
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
-- 平台币钱包表（含乐观锁）
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
-- 游戏币钱包表
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
-- 游戏表
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
-- 游戏币种表
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
-- 充值订单表
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
-- 提现订单表
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
-- 兑换记录表
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
-- 平台流水表
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
-- 支付方式表
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
-- 公告表
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
-- 平台配置表
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
-- 语言定义表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_language` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `code` VARCHAR(10) NOT NULL COMMENT '语言代码: en-US/zh-CN/ja-JP/ko-KR',
    `name` VARCHAR(50) NOT NULL COMMENT '语言名称',
    `native_name` VARCHAR(50) NOT NULL COMMENT '本地语名称: English/中文/日本語/한국어',
    `icon` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '国旗代码: us/cn/jp/kr',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='语言定义表';

-- ============================================================
-- 翻译文本表
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_translation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `group` VARCHAR(50) NOT NULL DEFAULT 'app' COMMENT '翻译分组: app/auth/wallet/exchange/withdraw/game/error',
    `key` VARCHAR(200) NOT NULL COMMENT '翻译键名',
    `lang_code` VARCHAR(10) NOT NULL COMMENT '语言代码: en-US/zh-CN',
    `value` TEXT COMMENT '翻译文本',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_key_lang` (`group`, `key`, `lang_code`),
    KEY `idx_lang_code` (`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='翻译文本表';

-- ============================================================
-- 第三方登录关联表
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
-- 用户登录会话表
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
-- 实名认证表 (KYC)
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
-- 用户收款账户表
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
-- 提现限额规则表
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
-- 游戏区服表
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
-- 游戏记录表
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
-- 风控规则表
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
-- 风控日志表
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
-- 日统计快照表
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
-- 游戏分类表
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
-- 游戏-分类关联表（多对多）
-- ============================================================
CREATE TABLE IF NOT EXISTS `erik_game_category_rel` (
    `game_id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`game_id`, `category_id`),
    KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏-分类关联表';

-- ============================================================
-- 排行榜定义表
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
-- 优惠券表
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
-- 用户优惠券表
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
-- 国家差异化配置表
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
-- 平台收益记录表
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
-- 通知表
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
-- 推荐关系表
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
-- 推荐奖励表
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
-- 2FA 双因素认证表
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
-- 种子数据
-- ============================================================

-- 默认管理员角色
INSERT IGNORE INTO `erik_admin_role` (`id`, `name`, `slug`, `description`, `status`) VALUES
(10000000000000001, '超级管理员', 'super_admin', '系统超级管理员，拥有所有权限', 1);

-- 菜单权限 (type=1)
INSERT IGNORE INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000001, '0', '仪表盘',    'dashboard',     1, 'dashboard', '/dashboard',        1, NOW(), NOW()),
(21000000000000002, '0', '用户管理',  'user',           1, 'people',    '/admin/user',        2, NOW(), NOW()),
(21000000000000003, '0', '角色管理',  'role',           1, 'shield',    '/admin/role',        3, NOW(), NOW()),
(21000000000000004, '0', '权限管理',  'permission',     1, 'lock',      '/admin/permission',  4, NOW(), NOW()),
(21000000000000005, '0', '系统配置',  'config',         1, 'settings',  '/admin/config',      5, NOW(), NOW()),
(21000000000000006, '0', '操作日志',  'log',            1, 'article',   '/admin/log',         6, NOW(), NOW());

-- 按钮权限 (type=2)
INSERT IGNORE INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000011, 21000000000000002, '批量删除',     'batch.destroy', 2, '', '', 1, NOW(), NOW()),
(21000000000000012, 21000000000000002, '批量启用/禁用', 'batch.status', 2, '', '', 2, NOW(), NOW()),
(21000000000000013, 21000000000000002, '导入用户',     'import.users',  2, '', '', 3, NOW(), NOW()),
(21000000000000014, 21000000000000002, '导出Excel',     'export.excel',  2, '', '', 4, NOW(), NOW()),
(21000000000000015, 21000000000000002, '导出PDF',       'export.pdf',    2, '', '', 5, NOW(), NOW()),
(21000000000000016, 21000000000000002, '文件上传',     'upload',         2, '', '', 6, NOW(), NOW());

-- API 权限 (type=3)
INSERT IGNORE INTO `erik_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
(21000000000000021, 21000000000000001, '查看仪表盘',   'get.admin/dashboard', 3, '', '', 1, NOW(), NOW()),
(21000000000000031, 21000000000000002, '查看用户',     'get.admin/user',             3, '', '', 1, NOW(), NOW()),
(21000000000000032, 21000000000000002, '创建用户',     'post.admin/user',            3, '', '', 2, NOW(), NOW()),
(21000000000000033, 21000000000000002, '更新用户',     'put.admin/user',             3, '', '', 3, NOW(), NOW()),
(21000000000000034, 21000000000000002, '删除用户',     'delete.admin/user',          3, '', '', 4, NOW(), NOW()),
(21000000000000035, 21000000000000002, '批量删除用户', 'post.admin/user/batch/destroy', 3, '', '', 5, NOW(), NOW()),
(21000000000000036, 21000000000000002, '批量启禁用',   'post.admin/user/batch/status',  3, '', '', 6, NOW(), NOW()),
(21000000000000041, 21000000000000003, '查看角色', 'get.admin/role',    3, '', '', 1, NOW(), NOW()),
(21000000000000042, 21000000000000003, '创建角色', 'post.admin/role',   3, '', '', 2, NOW(), NOW()),
(21000000000000043, 21000000000000003, '更新角色', 'put.admin/role',    3, '', '', 3, NOW(), NOW()),
(21000000000000044, 21000000000000003, '删除角色', 'delete.admin/role', 3, '', '', 4, NOW(), NOW()),
(21000000000000051, 21000000000000004, '查看权限', 'get.admin/permission',    3, '', '', 1, NOW(), NOW()),
(21000000000000052, 21000000000000004, '创建权限', 'post.admin/permission',   3, '', '', 2, NOW(), NOW()),
(21000000000000053, 21000000000000004, '更新权限', 'put.admin/permission',    3, '', '', 3, NOW(), NOW()),
(21000000000000054, 21000000000000004, '删除权限', 'delete.admin/permission', 3, '', '', 4, NOW(), NOW()),
(21000000000000061, 21000000000000005, '查看配置', 'get.admin/config',    3, '', '', 1, NOW(), NOW()),
(21000000000000062, 21000000000000005, '创建配置', 'post.admin/config',   3, '', '', 2, NOW(), NOW()),
(21000000000000063, 21000000000000005, '更新配置', 'put.admin/config',    3, '', '', 3, NOW(), NOW()),
(21000000000000064, 21000000000000005, '删除配置', 'delete.admin/config', 3, '', '', 4, NOW(), NOW()),
(21000000000000071, 21000000000000006, '查看日志', 'get.admin/log', 3, '', '', 1, NOW(), NOW()),
(21000000000000081, '0', '个人中心-更新信息', 'put.admin/profile',         3, '', '', 1, NOW(), NOW()),
(21000000000000082, '0', '个人中心-修改密码', 'put.admin/profile/password', 3, '', '', 2, NOW(), NOW()),
(21000000000000083, '0', '个人中心-登出',     'post.admin/profile/logout',  3, '', '', 3, NOW(), NOW()),
(21000000000000091, '0', '导出Excel', 'post.admin/export/excel', 3, '', '', 1, NOW(), NOW()),
(21000000000000092, '0', '导出PDF',   'post.admin/export/pdf',   3, '', '', 2, NOW(), NOW()),
(21000000000000093, '0', '导入用户', 'post.admin/import/users', 3, '', '', 1, NOW(), NOW()),
(21000000000000094, '0', '文件上传', 'post.admin/upload', 3, '', '', 1, NOW(), NOW());

-- 超级管理员角色关联所有权限（幂等：跳过已存在的关联）
INSERT INTO `erik_admin_role_permission` (`role_id`, `permission_id`)
SELECT 10000000000000001, `id` FROM `erik_admin_permission`
WHERE `id` NOT IN (
    SELECT `permission_id` FROM `erik_admin_role_permission` WHERE `role_id` = 10000000000000001
);

-- 默认平台配置
INSERT IGNORE INTO `erik_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20000000000000001, 'withdraw', 'global_switch', '1', 'bool', '全局提现开关: 1=允许提现 0=禁止提现'),
(20000000000000002, 'withdraw', 'auto_approve_threshold', '100.0000', 'decimal', '自动审核阈值（平台币），低于此金额自动通过'),
(20000000000000003, 'withdraw', 'daily_limit', '10000.0000', 'decimal', '每人每日提现上限（平台币）'),
(20000000000000004, 'withdraw', 'min_amount', '1.0000', 'decimal', '单笔最低提现金额（平台币）'),
(20000000000000005, 'payment', 'default_exchange_rate', '1.00000000', 'decimal', '默认平台币兑USD汇率'),
(20000000000000006, 'system', 'site_name', 'Global Game Platform', 'string', '平台名称');

-- 推荐配置
INSERT IGNORE INTO `erik_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(60000000000000001, 'referral', 'signup_reward', '5.0000', 'decimal', '注册奖励(平台币)，推荐人和被推荐人各得'),
(60000000000000002, 'referral', 'deposit_commission_pct', '5.00', 'decimal', '充值返佣比例(%)');

-- 语言
INSERT IGNORE INTO `erik_language` (`id`, `code`, `name`, `native_name`, `icon`, `status`, `sort`) VALUES
(30000000000000001, 'en-US', 'English', 'English', 'us', 1, 1),
(30000000000000002, 'zh-CN', 'Chinese (Simplified)', '简体中文', 'cn', 1, 2),
(30000000000000003, 'ja-JP', 'Japanese', '日本語', 'jp', 1, 3),
(30000000000000004, 'ko-KR', 'Korean', '한국어', 'kr', 1, 4);

-- 翻译文本
INSERT IGNORE INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
(30000000000000101, 'auth', 'register_success', 'en-US', 'Registration successful'),
(30000000000000102, 'auth', 'register_success', 'zh-CN', '注册成功'),
(30000000000000103, 'auth', 'login_success', 'en-US', 'Login successful'),
(30000000000000104, 'auth', 'login_success', 'zh-CN', '登录成功'),
(30000000000000105, 'auth', 'username_exists', 'en-US', 'Username already exists'),
(30000000000000106, 'auth', 'username_exists', 'zh-CN', '用户名已被注册'),
(30000000000000107, 'auth', 'wrong_credentials', 'en-US', 'Invalid username or password'),
(30000000000000108, 'auth', 'wrong_credentials', 'zh-CN', '用户名或密码错误'),
(30000000000000109, 'auth', 'account_disabled', 'en-US', 'Account has been disabled'),
(30000000000000110, 'auth', 'account_disabled', 'zh-CN', '账号已被禁用'),
(30000000000000111, 'auth', 'token_refresh_failed', 'en-US', 'Token refresh failed'),
(30000000000000112, 'auth', 'token_refresh_failed', 'zh-CN', 'Token刷新失败'),
(30000000000000113, 'auth', 'not_logged_in', 'en-US', 'Please login first'),
(30000000000000114, 'auth', 'not_logged_in', 'zh-CN', '请先登录'),
(30000000000000115, 'auth', 'token_expired', 'en-US', 'Token expired or invalid'),
(30000000000000116, 'auth', 'token_expired', 'zh-CN', 'Token已过期或无效'),
(30000000000000117, 'auth', 'user_not_found', 'en-US', 'User not found or disabled'),
(30000000000000118, 'auth', 'user_not_found', 'zh-CN', '用户不存在或已禁用'),
(30000000000000201, 'wallet', 'not_found', 'en-US', 'Wallet not found'),
(30000000000000202, 'wallet', 'not_found', 'zh-CN', '钱包不存在'),
(30000000000000203, 'wallet', 'insufficient_balance', 'en-US', 'Insufficient platform balance'),
(30000000000000204, 'wallet', 'insufficient_balance', 'zh-CN', '平台币余额不足'),
(30000000000000301, 'exchange', 'success', 'en-US', 'Exchange successful'),
(30000000000000302, 'exchange', 'success', 'zh-CN', '兑换成功'),
(30000000000000303, 'exchange', 'game_unavailable', 'en-US', 'Game is not available'),
(30000000000000304, 'exchange', 'game_unavailable', 'zh-CN', '游戏不可用'),
(30000000000000305, 'exchange', 'currency_not_found', 'en-US', 'Game currency not found'),
(30000000000000306, 'exchange', 'currency_not_found', 'zh-CN', '游戏币种不存在'),
(30000000000000307, 'exchange', 'insufficient_game_balance', 'en-US', 'Insufficient game balance'),
(30000000000000308, 'exchange', 'insufficient_game_balance', 'zh-CN', '游戏币余额不足'),
(30000000000000309, 'exchange', 'fee_too_high', 'en-US', 'Exchange amount too low to cover fees'),
(30000000000000310, 'exchange', 'fee_too_high', 'zh-CN', '兑换金额不足以支付手续费'),
(30000000000000401, 'withdraw', 'disabled', 'en-US', 'Withdrawal is temporarily disabled'),
(30000000000000402, 'withdraw', 'disabled', 'zh-CN', '提现功能暂时关闭'),
(30000000000000403, 'withdraw', 'below_min', 'en-US', 'Amount below minimum withdrawal limit'),
(30000000000000404, 'withdraw', 'below_min', 'zh-CN', '提现金额不能低于最低限额'),
(30000000000000405, 'withdraw', 'daily_limit_exceeded', 'en-US', 'Daily withdrawal limit exceeded'),
(30000000000000406, 'withdraw', 'daily_limit_exceeded', 'zh-CN', '超过每日提现限额'),
(30000000000000407, 'withdraw', 'submitted', 'en-US', 'Withdrawal request submitted'),
(30000000000000408, 'withdraw', 'submitted', 'zh-CN', '提现申请已提交'),
(30000000000000409, 'withdraw', 'auto_approved', 'en-US', 'Withdrawal auto-approved'),
(30000000000000410, 'withdraw', 'auto_approved', 'zh-CN', '提现申请已自动通过'),
(30000000000000411, 'withdraw', 'order_not_pending', 'en-US', 'Order is not pending review'),
(30000000000000412, 'withdraw', 'order_not_pending', 'zh-CN', '订单状态不是待审核'),
(30000000000000501, 'deposit', 'order_created', 'en-US', 'Order created successfully'),
(30000000000000502, 'deposit', 'order_created', 'zh-CN', '订单创建成功'),
(30000000000000601, 'game', 'not_found', 'en-US', 'Game not found'),
(30000000000000602, 'game', 'not_found', 'zh-CN', '游戏不存在'),
(30000000000000603, 'game', 'created', 'en-US', 'Game created successfully'),
(30000000000000604, 'game', 'created', 'zh-CN', '游戏创建成功'),
(30000000000000605, 'game', 'updated', 'en-US', 'Game updated successfully'),
(30000000000000606, 'game', 'updated', 'zh-CN', '更新成功'),
(30000000000000607, 'game', 'deleted', 'en-US', 'Game deleted successfully'),
(30000000000000608, 'game', 'deleted', 'zh-CN', '删除成功'),
(30000000000000609, 'game', 'slug_exists', 'en-US', 'Game slug already exists'),
(30000000000000610, 'game', 'slug_exists', 'zh-CN', '游戏标识已存在'),
(30000000000000611, 'game', 'currency_updated', 'en-US', 'Currency updated successfully'),
(30000000000000612, 'game', 'currency_updated', 'zh-CN', '币种更新成功'),
(30000000000000701, 'admin', 'withdraw_approved', 'en-US', 'Withdrawal approved'),
(30000000000000702, 'admin', 'withdraw_approved', 'zh-CN', '已通过'),
(30000000000000703, 'admin', 'withdraw_rejected', 'en-US', 'Withdrawal rejected'),
(30000000000000704, 'admin', 'withdraw_rejected', 'zh-CN', '已拒绝'),
(30000000000000705, 'admin', 'switch_on', 'en-US', 'Withdrawal function enabled'),
(30000000000000706, 'admin', 'switch_on', 'zh-CN', '提现功能已开启'),
(30000000000000707, 'admin', 'switch_off', 'en-US', 'Withdrawal function disabled'),
(30000000000000708, 'admin', 'switch_off', 'zh-CN', '提现功能已关闭'),
(30000000000000709, 'admin', 'limits_updated', 'en-US', 'Limits updated'),
(30000000000000710, 'admin', 'limits_updated', 'zh-CN', '限额设置已更新'),
(30000000000000711, 'admin', 'announcement_created', 'en-US', 'Announcement published'),
(30000000000000712, 'admin', 'announcement_created', 'zh-CN', '公告发布成功'),
(30000000000000713, 'admin', 'not_found', 'en-US', 'Resource not found'),
(30000000000000714, 'admin', 'not_found', 'zh-CN', '资源不存在'),
(30000000000000715, 'admin', 'user_updated', 'en-US', 'User updated successfully'),
(30000000000000716, 'admin', 'user_updated', 'zh-CN', '更新成功'),
(30000000000000717, 'admin', 'payment_updated', 'en-US', 'Payment method updated'),
(30000000000000718, 'admin', 'payment_updated', 'zh-CN', '已更新'),
(30000000000000719, 'admin', 'announcement_not_found', 'en-US', 'Announcement not found'),
(30000000000000720, 'admin', 'announcement_not_found', 'zh-CN', '公告不存在');

-- 默认风控规则
INSERT IGNORE INTO `erik_risk_rule` (`id`, `name`, `type`, `config`, `action`, `priority`, `status`) VALUES
(40000000000000001, 'IP黑名单检测', 'ip_blacklist', '{"blacklist":[]}', 'block', 100, 1),
(40000000000000002, '单笔大额充值预警', 'amount_anomaly', '{"min_amount":"5000","currency":"USD"}', 'warn', 50, 1),
(40000000000000003, '高频提现检测', 'frequency', '{"window_minutes":60,"max_count":5}', 'warn', 50, 1),
(40000000000000004, '短时多账号检测', 'velocity', '{"window_minutes":10,"max_accounts":3,"same_ip":true}', 'block', 80, 1);

-- 默认提现限额规则
INSERT IGNORE INTO `erik_withdraw_limit` (`id`, `user_level`, `single_min`, `single_max`, `daily_limit`, `monthly_limit`, `fee_pct`, `fee_max`, `auto_approve_threshold`) VALUES
(40000000000000010, 'default', 1.0000, 1000.0000, 10000.0000, 50000.0000, 1.00, 50.0000, 100.0000),
(40000000000000011, 'verified', 1.0000, 5000.0000, 50000.0000, 200000.0000, 0.50, 25.0000, 500.0000),
(40000000000000012, 'vip', 1.0000, 20000.0000, 200000.0000, 1000000.0000, 0.00, 0.0000, 5000.0000);

-- 默认游戏分类
INSERT IGNORE INTO `erik_game_category` (`id`, `name`, `slug`, `icon`, `sort`, `status`) VALUES
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

-- 默认国家配置
INSERT IGNORE INTO `erik_country_config` (`id`, `country_code`, `currency`, `payment_methods`, `withdraw_methods`, `min_deposit`) VALUES
(50000000000000101, 'US', 'USD', '["stripe","paypal","crypto"]', '["paypal","bank","crypto"]', 1.0000),
(50000000000000102, 'CN', 'CNY', '["alipay","wechat"]', '["alipay","bank"]', 10.0000),
(50000000000000103, 'JP', 'JPY', '["stripe","paypal"]', '["paypal","bank"]', 100.0000),
(50000000000000104, 'KR', 'KRW', '["stripe","paypal"]', '["paypal","bank"]', 1000.0000),
(50000000000000105, 'GB', 'GBP', '["stripe","paypal"]', '["paypal","bank"]', 1.0000),
(50000000000000106, 'DE', 'EUR', '["stripe","paypal"]', '["paypal","bank"]', 1.0000),
(50000000000000107, 'BR', 'BRL', '["stripe"]', '["bank"]', 1.0000),
(50000000000000108, 'IN', 'INR', '["stripe"]', '["bank"]', 50.0000);

COMMIT;
