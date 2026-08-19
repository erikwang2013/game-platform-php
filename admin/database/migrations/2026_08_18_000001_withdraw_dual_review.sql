-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 提现双重审核：confirmed_by / confirmed_at + require_dual_review 配置
-- Applied by ops / installer; not referenced from PHP at runtime.

ALTER TABLE `erik_withdraw_order`
    ADD COLUMN `confirmed_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '二次确认管理员ID，须与 reviewer_id 不同' AFTER `reviewed_at`,
    ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL COMMENT '二次确认时间' AFTER `confirmed_by`;

INSERT IGNORE INTO `erik_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20000000000000007, 'withdraw', 'require_dual_review', '1', 'bool', '提现双重审核: 1=通过后须另一管理员确认方可打款 0=关闭');
