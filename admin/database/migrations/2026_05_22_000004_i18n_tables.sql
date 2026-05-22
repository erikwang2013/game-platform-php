-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 国际化基础表（语言定义 + 翻译文本）
-- ============================================================

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
-- 插入语言
-- ============================================================
INSERT INTO `erik_language` (`id`, `code`, `name`, `native_name`, `icon`, `status`, `sort`) VALUES
(30000000000000001, 'en-US', 'English', 'English', 'us', 1, 1),
(30000000000000002, 'zh-CN', 'Chinese (Simplified)', '简体中文', 'cn', 1, 2),
(30000000000000003, 'ja-JP', 'Japanese', '日本語', 'jp', 1, 3),
(30000000000000004, 'ko-KR', 'Korean', '한국어', 'kr', 1, 4);

-- ============================================================
-- 插入翻译文本（en-US + zh-CN）
-- ============================================================

-- auth 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
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
(30000000000000118, 'auth', 'user_not_found', 'zh-CN', '用户不存在或已禁用');

-- wallet 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
(30000000000000201, 'wallet', 'not_found', 'en-US', 'Wallet not found'),
(30000000000000202, 'wallet', 'not_found', 'zh-CN', '钱包不存在'),
(30000000000000203, 'wallet', 'insufficient_balance', 'en-US', 'Insufficient platform balance'),
(30000000000000204, 'wallet', 'insufficient_balance', 'zh-CN', '平台币余额不足');

-- exchange 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
(30000000000000301, 'exchange', 'success', 'en-US', 'Exchange successful'),
(30000000000000302, 'exchange', 'success', 'zh-CN', '兑换成功'),
(30000000000000303, 'exchange', 'game_unavailable', 'en-US', 'Game is not available'),
(30000000000000304, 'exchange', 'game_unavailable', 'zh-CN', '游戏不可用'),
(30000000000000305, 'exchange', 'currency_not_found', 'en-US', 'Game currency not found'),
(30000000000000306, 'exchange', 'currency_not_found', 'zh-CN', '游戏币种不存在'),
(30000000000000307, 'exchange', 'insufficient_game_balance', 'en-US', 'Insufficient game balance'),
(30000000000000308, 'exchange', 'insufficient_game_balance', 'zh-CN', '游戏币余额不足'),
(30000000000000309, 'exchange', 'fee_too_high', 'en-US', 'Exchange amount too low to cover fees'),
(30000000000000310, 'exchange', 'fee_too_high', 'zh-CN', '兑换金额不足以支付手续费');

-- withdraw 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
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
(30000000000000412, 'withdraw', 'order_not_pending', 'zh-CN', '订单状态不是待审核');

-- deposit 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
(30000000000000501, 'deposit', 'order_created', 'en-US', 'Order created successfully'),
(30000000000000502, 'deposit', 'order_created', 'zh-CN', '订单创建成功');

-- game 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
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
(30000000000000612, 'game', 'currency_updated', 'zh-CN', '币种更新成功');

-- admin 分组
INSERT INTO `erik_translation` (`id`, `group`, `key`, `lang_code`, `value`) VALUES
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
