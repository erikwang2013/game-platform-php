-- ============================================================
-- 风控增强迁移（H4）：scope 列 / 完整日志 / IP 哈希化
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_risk_enhancement.sql
-- ============================================================

-- 1. 风控规则表：新增 scope 生效范围列
ALTER TABLE `game_risk_rule`
    ADD COLUMN `scope` VARCHAR(30) NOT NULL DEFAULT 'all' COMMENT '生效范围: all=全环节/deposit/withdraw/exchange/login' AFTER `type`;

-- 2. 风控日志表：完整命中详情 + 哈希列（PII 只存 hash）
ALTER TABLE `game_risk_log`
    ADD COLUMN `detail` TEXT COMMENT '完整命中详情（不被截断）' AFTER `result`,
    ADD COLUMN `ip_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'IP sha256（不存明文）' AFTER `detail`,
    ADD COLUMN `fp_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '设备指纹 sha256' AFTER `ip_hash`,
    ADD COLUMN `user_agent_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'User-Agent sha256' AFTER `fp_hash`,
    ADD KEY `idx_ip_hash_created` (`ip_hash`, `created_at`),
    ADD KEY `idx_fp_hash_created` (`fp_hash`, `created_at`),
    ADD KEY `idx_action_created` (`action`, `created_at`);

-- 3. IP 信誉表：明文 IP → sha256 哈希
ALTER TABLE `game_ip_reputation`
    DROP INDEX `uk_ip`,
    CHANGE COLUMN `ip` `ip_hash` VARCHAR(64) NOT NULL COMMENT 'IP sha256（不存明文，支持 IPv4/IPv6）' AFTER `id`,
    ADD UNIQUE KEY `uk_ip_hash` (`ip_hash`);

-- 4. 4 条新评估器种子规则（status=0 灰度禁用，评估器上线后逐条放量）
INSERT IGNORE INTO `game_risk_rule` (`id`, `name`, `type`, `scope`, `config`, `action`, `priority`, `status`) VALUES
(40000000000000005, '新设备提现检测', 'device_fingerprint', 'all', '{"max_accounts_per_device":5,"new_device_lookback_hours":24,"new_device_withdraw_block":true}', 'block', 90, 0),
(40000000000000006, 'IP 信誉检测', 'ip_reputation', 'all', '{"block_score_below":30,"warn_score_below":60,"block_unknown":false}', 'block', 90, 0),
(40000000000000007, '设备团伙关联检测', 'device_account_graph', 'all', '{"cluster_threshold":6,"frozen_sibling_block":true,"max_accounts_per_device":50}', 'warn', 70, 0),
(40000000000000008, '提现模式异常检测', 'withdraw_pattern', 'withdraw', '{"window_minutes":60,"max_applies":5,"single_hard_cap":"50000","drain_ratio":"0.99","sigma_window_days":90,"sigma_multiplier":3,"fast_interval_seconds":20,"fast_interval_min_count":5}', 'warn', 70, 0);

-- 5. 存量明文 IP 行哈希化（执行后原明文不可恢复，如需回滚请先备份本表）
UPDATE `game_ip_reputation` SET `ip_hash` = SHA2(`ip_hash`, 256) WHERE `ip_hash` NOT REGEXP '^[0-9a-f]{64}$';
