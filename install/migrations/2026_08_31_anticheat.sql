-- ============================================================
-- 反作弊迁移（H5）：对局日志列补齐 + 汇总/事件/信任分三表 + 种子规则
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_anticheat.sql
-- ============================================================

-- 1. 对局日志：补 round_id/bet_amount/win_amount 三列缺口 + 反作弊 7 列
--    PII 原则（评审修订 #1）：IP/UA 只存 sha256（ip_hash/user_agent_hash），device_id 可明文
ALTER TABLE `game_game_play_log`
    ADD COLUMN `round_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '对局ID' AFTER `session_id`,
    ADD COLUMN `bet_amount` DECIMAL(18,4) NULL COMMENT '下注额' AFTER `game_amount_after`,
    ADD COLUMN `win_amount` DECIMAL(18,4) NULL COMMENT '赢额' AFTER `bet_amount`,
    ADD COLUMN `ip_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'IP sha256（不存明文）' AFTER `platform_amount_change`,
    ADD COLUMN `user_agent_hash` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'User-Agent sha256' AFTER `ip_hash`,
    ADD COLUMN `device_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '设备ID' AFTER `user_agent_hash`,
    ADD COLUMN `ended_at_round` DATETIME NULL COMMENT '对局结束时间(区别于session级ended_at)' AFTER `device_id`,
    ADD COLUMN `level_id` INT NULL COMMENT '关卡ID' AFTER `ended_at_round`,
    ADD COLUMN `move_count` INT NULL COMMENT '出招次数' AFTER `level_id`,
    ADD COLUMN `result` VARCHAR(10) NULL COMMENT 'win/fail' AFTER `move_count`,
    ADD KEY `idx_ip_hash_created` (`ip_hash`, `created_at`),
    ADD KEY `idx_device` (`device_id`),
    ADD KEY `idx_round` (`round_id`);

-- 2. 反作弊日汇总表（离线检测输入：每小时扫增量 UPSERT，幂等）
CREATE TABLE IF NOT EXISTS `game_anticheat_daily_stat` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '雪花ID',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `stat_date` DATE NOT NULL,
    `rounds` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对局数(去重round_id)',
    `wins` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '胜局数',
    `bets` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '累计下注额',
    `avg_bet` DECIMAL(18,4) NULL COMMENT '平均注码(批内聚合,非全天精确值)',
    `std_bet` DECIMAL(18,4) NULL COMMENT '注码标准差(批内聚合)',
    `wins_total` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '累计赢额',
    `plays_30d` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '30日对局数(后置)',
    `wins_30d` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '30日胜局数(后置)',
    `active_seconds` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '活跃秒数(后置)',
    `moves_per_sec_p50` DECIMAL(6,3) NULL COMMENT '出招频率p50(后置)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_game_date` (`user_id`, `game_id`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='反作弊日汇总表';

-- 3. 反作弊事件表（幂等键 user_id+rule_type+stat_date：同一天同规则只记一次，重跑不重复扣分）
CREATE TABLE IF NOT EXISTS `game_anticheat_event` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '雪花ID',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `rule_type` VARCHAR(30) NOT NULL COMMENT '规则类型: anticheat_bet_pattern/anticheat_duration/anticheat_rate',
    `rule_name` VARCHAR(100) NOT NULL DEFAULT '',
    `severity` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '1=低 2=中 3=高',
    `score_delta` INT NOT NULL DEFAULT 0 COMMENT '信任分变动(负=扣分)',
    `action` VARCHAR(20) NOT NULL DEFAULT 'warn' COMMENT '规则动作: log/warn/block',
    `evidence` TEXT COMMENT '命中证据(JSON)',
    `round_id` VARCHAR(64) NOT NULL DEFAULT '',
    `stat_date` DATE NOT NULL DEFAULT '1970-01-01' COMMENT '统计日期(幂等键)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open/confirmed/whitelisted/closed',
    `reviewer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人admin_id',
    `review_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '审核备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_rule_date` (`user_id`, `rule_type`, `stat_date`),
    KEY `idx_status_created` (`status`, `created_at`),
    KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='反作弊事件表';

-- 4. 用户信任分表
CREATE TABLE IF NOT EXISTS `game_user_trust` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '雪花ID',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `score` SMALLINT NOT NULL DEFAULT 100 COMMENT '信任分 0-100',
    `band` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'normal/observe/restrict/freeze',
    `hit_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计命中次数',
    `last_hit_at` DATETIME NULL COMMENT '最近命中时间',
    `whitelisted` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服加白后不再自动扣分',
    `whitelist_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '加白操作人admin_id',
    `whitelist_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '加白备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户信任分表';

-- 5. 种子规则：下注模式检测 warn 灰启（status=0，放量前手动启用）
--    阈值语义（设计 §3.2）：fixed=注码变异系数 < fixed_cv 且 n >= min_rounds；
--    martingale/anti_martingale=输/赢后翻倍注码占比 > 触发阈值；arithmetic=连续等差 diff >= ar_run；
--    四模板取最高命中率，> trigger_ratio 触发；score_delta 为单次扣分（幂等键防重跑重复扣）。
INSERT IGNORE INTO `game_risk_rule` (`id`, `name`, `type`, `scope`, `config`, `action`, `priority`, `status`) VALUES
(40000000000000009, '下注模式异常检测', 'anticheat_bet_pattern', 'all', '{"min_rounds":30,"fixed_cv":0.02,"trigger_ratio":0.6,"ratio_tolerance":0.005,"ar_run":8,"ar_diff_tolerance":0.01,"score_delta":-10,"window_days":7}', 'warn', 60, 0);
