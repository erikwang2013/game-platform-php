-- ============================================================
-- L3 本地化与合规迁移：国家扩展 + 支付/提现规则细化 + KYC/AML 数据模型 + 语言扩展
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_localization_compliance.sql
-- ============================================================

-- ============================================================
-- 1. game_country_config 扩展：lang_prefix（fromLang 查表化）+ 支付/提现规则字段
-- ============================================================
ALTER TABLE `game_country_config`
    ADD COLUMN `lang_prefix` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '语言前缀->国家映射(zh/en/ja/...)，CountryConfig::fromLang() 查表用，空=无映射' AFTER `country_code`,
    ADD COLUMN `max_deposit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '最高单笔充值额(0=不限)' AFTER `min_deposit`,
    ADD COLUMN `daily_deposit_limit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '单日充值限额(0=不限)' AFTER `max_deposit`,
    ADD COLUMN `withdraw_fee_percent` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT '提现费率%(对账参考值，真实费率以网关侧为准)' AFTER `withdraw_methods`,
    ADD COLUMN `withdraw_min` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '最低提现额(0=不限)' AFTER `withdraw_fee_percent`,
    ADD COLUMN `settlement_days` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '结算周期 T+N(0=实时)' AFTER `withdraw_min`;

-- 2. 现有国家回填 lang_prefix（与迁移前 fromLang() 硬编码映射完全一致，保证无回归）
UPDATE `game_country_config` SET `lang_prefix` = 'en' WHERE `country_code` = 'US';
UPDATE `game_country_config` SET `lang_prefix` = 'zh' WHERE `country_code` = 'CN';
UPDATE `game_country_config` SET `lang_prefix` = 'ja' WHERE `country_code` = 'JP';
UPDATE `game_country_config` SET `lang_prefix` = 'ko' WHERE `country_code` = 'KR';
UPDATE `game_country_config` SET `lang_prefix` = 'de' WHERE `country_code` = 'DE';
UPDATE `game_country_config` SET `lang_prefix` = 'pt' WHERE `country_code` = 'BR';
UPDATE `game_country_config` SET `lang_prefix` = 'hi' WHERE `country_code` = 'IN';

-- 3. 新增 10 国种子：payment_methods 为规则 JSON（键=网关 provider，均可被 GatewayFactory::resolve() 解析）
INSERT IGNORE INTO `game_country_config`
    (`id`, `country_code`, `lang_prefix`, `currency`, `payment_methods`, `withdraw_methods`, `min_deposit`, `max_deposit`, `daily_deposit_limit`, `withdraw_fee_percent`, `withdraw_min`, `settlement_days`, `status`) VALUES
(50000000000000109, 'SG', '',  'SGD', '{"stripe":{"enabled":true,"min":"5","max":"5000","fee_percent":"2.9"},"grabpay":{"enabled":true,"min":"1","max":"2000","fee_percent":"1.5"},"nowpayments":{"enabled":true,"min":"10","max":"50000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"10","max":"5000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"10","max":"10000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"20","max":"50000","fee_percent":"0.0"}}', 5.0000, 5000.0000, 10000.0000, 0.5000, 10.0000, 1, 1),
(50000000000000110, 'MY', 'ms', 'MYR', '{"stripe":{"enabled":true,"min":"10","max":"5000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"20","max":"50000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"20","max":"5000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"20","max":"10000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"50","max":"50000","fee_percent":"0.0"}}', 10.0000, 5000.0000, 10000.0000, 0.5000, 20.0000, 1, 1),
(50000000000000111, 'TH', 'th', 'THB', '{"stripe":{"enabled":true,"min":"100","max":"50000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"100","max":"500000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"200","max":"50000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"100","max":"100000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"500","max":"500000","fee_percent":"0.0"}}', 100.0000, 50000.0000, 100000.0000, 0.5000, 100.0000, 1, 1),
(50000000000000112, 'ID', 'id', 'IDR', '{"stripe":{"enabled":true,"min":"10000","max":"5000000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"10000","max":"50000000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"20000","max":"5000000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"10000","max":"10000000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"50000","max":"50000000","fee_percent":"0.0"}}', 10000.0000, 5000000.0000, 10000000.0000, 0.5000, 10000.0000, 2, 1),
(50000000000000113, 'VN', 'vi', 'VND', '{"stripe":{"enabled":true,"min":"50000","max":"20000000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"50000","max":"200000000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"100000","max":"20000000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"50000","max":"50000000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"200000","max":"200000000","fee_percent":"0.0"}}', 50000.0000, 20000000.0000, 50000000.0000, 0.5000, 50000.0000, 1, 1),
(50000000000000114, 'PH', 'tl', 'PHP', '{"gcash":{"enabled":true,"min":"100","max":"50000","fee_percent":"1.5"},"stripe":{"enabled":true,"min":"100","max":"50000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"200","max":"500000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"200","max":"50000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"100","max":"100000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"500","max":"500000","fee_percent":"0.0"}}', 100.0000, 50000.0000, 100000.0000, 0.5000, 100.0000, 2, 1),
(50000000000000115, 'ZA', 'af', 'ZAR', '{"stripe":{"enabled":true,"min":"20","max":"10000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"50","max":"100000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"50","max":"10000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"20","max":"20000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"100","max":"100000","fee_percent":"0.0"}}', 20.0000, 10000.0000, 20000.0000, 0.5000, 20.0000, 1, 1),
(50000000000000116, 'NG', 'yo', 'NGN', '{"paystack":{"enabled":true,"min":"500","max":"5000000","fee_percent":"1.5"},"stripe":{"enabled":true,"min":"500","max":"5000000","fee_percent":"2.9"},"nowpayments":{"enabled":true,"min":"1000","max":"50000000","fee_percent":"0.5"}}', '{"paypal":{"enabled":true,"min":"1000","max":"5000000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"500","max":"10000000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"2000","max":"50000000","fee_percent":"0.0"}}', 500.0000, 5000000.0000, 10000000.0000, 0.5000, 500.0000, 2, 1),
(50000000000000117, 'AR', 'es', 'ARS', '{"mercadopago":{"enabled":true,"min":"1000","max":"300000","fee_percent":"3.0"},"stripe":{"enabled":true,"min":"1000","max":"300000","fee_percent":"2.9"}}', '{"paypal":{"enabled":true,"min":"2000","max":"300000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"1000","max":"600000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"5000","max":"3000000","fee_percent":"0.0"}}', 1000.0000, 300000.0000, 600000.0000, 0.5000, 1000.0000, 1, 1),
(50000000000000118, 'CL', '',  'CLP', '{"mercadopago":{"enabled":true,"min":"1000","max":"300000","fee_percent":"3.0"},"stripe":{"enabled":true,"min":"1000","max":"300000","fee_percent":"2.9"}}', '{"paypal":{"enabled":true,"min":"2000","max":"300000","fee_percent":"1.0"},"bank":{"enabled":true,"min":"1000","max":"600000","fee_percent":"0.5"},"crypto":{"enabled":true,"min":"5000","max":"3000000","fee_percent":"0.0"}}', 1000.0000, 300000.0000, 600000.0000, 0.5000, 1000.0000, 1, 1);

-- ============================================================
-- 4. game_language 扩展：country_code 正式关联
-- ============================================================
ALTER TABLE `game_language`
    ADD COLUMN `country_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '关联国家(ISO 3166-1 alpha-2，空=通用)' AFTER `icon`;

-- 5. 现有语言回填国家关联
UPDATE `game_language` SET `country_code` = 'US' WHERE `code` = 'en-US';
UPDATE `game_language` SET `country_code` = 'CN' WHERE `code` = 'zh-CN';
UPDATE `game_language` SET `country_code` = 'JP' WHERE `code` = 'ja-JP';
UPDATE `game_language` SET `country_code` = 'KR' WHERE `code` = 'ko-KR';

-- 6. 新增语言种子（现有 7 国缺失语言 + 新增 10 国主语言）
INSERT IGNORE INTO `game_language` (`id`, `code`, `name`, `native_name`, `icon`, `country_code`, `status`, `sort`) VALUES
(30000000000000005, 'pt-BR', 'Portuguese (Brazil)', 'Português (Brasil)', 'br', 'BR', 1, 4),
(30000000000000006, 'hi-IN', 'Hindi', 'हिन्दी', 'in', 'IN', 1, 5),
(30000000000000007, 'de-DE', 'German', 'Deutsch', 'de', 'DE', 1, 6),
(30000000000000008, 'en-SG', 'English (Singapore)', 'English (Singapore)', 'sg', 'SG', 1, 7),
(30000000000000009, 'ms-MY', 'Malay', 'Bahasa Melayu', 'my', 'MY', 1, 8),
(30000000000000010, 'th-TH', 'Thai', 'ไทย', 'th', 'TH', 1, 9),
(30000000000000011, 'id-ID', 'Indonesian', 'Bahasa Indonesia', 'id', 'ID', 1, 10),
(30000000000000012, 'vi-VN', 'Vietnamese', 'Tiếng Việt', 'vn', 'VN', 1, 11),
(30000000000000013, 'tl-PH', 'Filipino', 'Filipino', 'ph', 'PH', 1, 12),
(30000000000000014, 'en-ZA', 'English (South Africa)', 'English (South Africa)', 'za', 'ZA', 1, 13),
(30000000000000015, 'en-NG', 'English (Nigeria)', 'English (Nigeria)', 'ng', 'NG', 1, 14),
(30000000000000016, 'es-AR', 'Spanish (Argentina)', 'Español (Argentina)', 'ar', 'AR', 1, 15),
(30000000000000017, 'es-CL', 'Spanish (Chile)', 'Español (Chile)', 'cl', 'CL', 1, 16);

-- ============================================================
-- 7. KYC/AML 合规数据模型（纯数据表 + 策略挂载点，判定逻辑由法务定义后实现）
-- ============================================================

-- KYC 等级定义
CREATE TABLE IF NOT EXISTS `game_kyc_level` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `level_no` TINYINT UNSIGNED NOT NULL COMMENT '等级序号，越大要求越高',
    `required_documents` JSON NOT NULL COMMENT '必需材料: ["identity","address_proof","face_verify"]',
    `age_min` TINYINT UNSIGNED NOT NULL DEFAULT 18 COMMENT '年龄门槛(岁)',
    `auto_approve` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否自动审批: 0=人工 1=自动',
    `review_by` VARCHAR(30) NOT NULL DEFAULT 'admin' COMMENT '审批角色: admin/compliance',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level_no` (`level_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC 等级定义表';

-- 用户 KYC 记录
CREATE TABLE IF NOT EXISTS `game_user_kyc` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `level_id` BIGINT UNSIGNED NOT NULL COMMENT '申请等级(game_kyc_level.id)',
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '审核状态: pending=待审 approved=通过 rejected=驳回',
    `documents` JSON NOT NULL COMMENT '提交材料: {"identity":"url","address_proof":"url","face_verify":"url"}',
    `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID(admin)',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `country_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '提交时所在国家(ISO 3166-1 alpha-2)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_country` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户 KYC 记录表';

-- AML 规则
CREATE TABLE IF NOT EXISTS `game_aml_rule` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '规则名称，如: 单日充值超限(US)',
    `country_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '适用国家(空=全球)',
    `daily_limit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '单日交易限额(0=不限)',
    `single_limit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '单笔交易限额(0=不限)',
    `velocity_window_seconds` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '频率统计窗口(秒，0=不启用)',
    `velocity_limit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '窗口内最大笔数(0=不启用)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_country_status` (`country_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AML 反洗钱规则表';

-- AML 命中记录
CREATE TABLE IF NOT EXISTS `game_aml_hit` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `rule_id` BIGINT UNSIGNED NOT NULL COMMENT '命中规则(game_aml_rule.id)',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '触发金额',
    `country_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '触发时所在国家',
    `status` ENUM('pending','cleared','escalated') NOT NULL DEFAULT 'pending' COMMENT '处置状态: pending=待处理 cleared=已解除 escalated=已升级人工',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_rule_user` (`rule_id`, `user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AML 命中记录表';
