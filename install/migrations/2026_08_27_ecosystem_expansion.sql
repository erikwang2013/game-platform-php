-- ============================================================
-- 生态扩展迁移：Phase 1-5 新增表与表结构变更
-- 2026-08-27
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_27_ecosystem_expansion.sql
--
-- ⚠ 本文件 2026-10-02 之前**寄居在 install/clickhouse.sql 第 17-180 行**，那是它唯一的落点，
--   而 clickhouse.sql 的唯一文档化用法是 `clickhouse-client < install/clickhouse.sql`
--   （docs/CLICKHOUSE_INSTALL.*:43，13 份）⇒ 这份 MySQL 脚本**在那之前从未被任何路径执行过**。
--   后果：`game_game.provider_config` 这一列、4 条 VIP 定义、12 条成就定义、4 条 feature flag
--   全部只存在于那个文件里，装了也是空的。
--   现独立成迁移，由 install/Installer.php:280 的 `glob(.../migrations/*.sql)` 自动应用。
--
-- 与 install.sql 的关系（2026-10-02 逐项核过，别照抄其它迁移那句「install.sql 已包含等价定义」）：
--   · 下面 10 张 CREATE TABLE —— install.sql **已包含**（且 install.sql 的版本更完备：带列 COMMENT、
--     BIGINT UNSIGNED、更完整的索引）⇒ 本迁移里的定义**仅为幂等与旧库兜底**，新装以 install.sql 为准。
--   · `game_game_play_log` 的 round_id/bet_amount/win_amount —— **已由 2026_08_31_anticheat.sql 添加**，
--     本迁移**不重复**。类型口径已判定为 **DECIMAL(18,4)**：`install.sql:587-588`（全新安装的 schema）
--     与 `2026_08_31_anticheat.sql`（已应用的迁移）两处一致；只有原 clickhouse.sql 写 `18,8` ——
--     而它从未被执行过，属**从未生效的旧写法**，别照抄回来。
--   · ⚠ `game_game.provider_config` —— **install.sql 没有这一列 ⇒ 本迁移是它唯一的来源。**
--   · ⚠ 三组种子（VIP 4 条 / 成就 12 条 / feature flag 4 条）—— **install.sql 没有 ⇒ 本迁移是唯一来源。**
--
-- 本迁移**可重复执行**：CREATE 用 IF NOT EXISTS；INSERT 用 INSERT IGNORE（主键去重，
-- 与 install.sql 既有写法一致）；ALTER 先用 information_schema 判列在不在
-- （MySQL 8 没有 ADD COLUMN IF NOT EXISTS，直接重复 ALTER 会报 Duplicate column）。
-- ============================================================

-- Phase 1: 表结构变更

-- ⚠ install.sql 的 `game_game` 至今没有 provider_config，而两棵树的 GameProvider 都在读它
--    （admin/app/provider/GameProvider.php:66,69、service/app/provider/GameProvider.php:45,48）。
--    缺列时 Eloquent 属性取到 null ⇒ empty() 为真 ⇒ **静默走默认分支**（不报错，故长期没被发现）。
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_game'
      AND COLUMN_NAME = 'provider_config'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `game_game` ADD COLUMN `provider_config` JSON NULL AFTER `sort`',
    'SELECT ''game_game.provider_config 已存在，跳过'' AS skipped');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Phase 2: 工单系统
CREATE TABLE IF NOT EXISTS `game_ticket` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'deposit/withdraw/game/account/other',
    `subject` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open/waiting/replied/closed',
    `priority` TINYINT NOT NULL DEFAULT 0,
    `assigned_to` BIGINT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_ticket_reply` (
    `id` BIGINT NOT NULL,
    `ticket_id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `content` TEXT NOT NULL,
    `is_admin` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 2: 推送
CREATE TABLE IF NOT EXISTS `game_device_token` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `platform` VARCHAR(20) NOT NULL COMMENT 'fcm/apns/harmonyos',
    `token` VARCHAR(500) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    UNIQUE INDEX `idx_token` (`token`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 3: VIP
CREATE TABLE IF NOT EXISTS `game_vip_level` (
    `id` BIGINT NOT NULL,
    `level` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `required_exp` INT NOT NULL,
    `benefits` JSON NULL COMMENT '{"exchange_discount":"0.02","withdraw_fee_discount":"0.10","rate_bonus":"0.001"}',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_user_vip` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `level` INT NOT NULL DEFAULT 0,
    `exp` INT NOT NULL DEFAULT 0,
    `total_exp` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_exp_log` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `amount` INT NOT NULL,
    `source` VARCHAR(30) NOT NULL COMMENT 'deposit/login/kyc/referral/achievement',
    `ref_type` VARCHAR(30) NULL,
    `ref_id` BIGINT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_source` (`user_id`, `source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 3: 成就
CREATE TABLE IF NOT EXISTS `game_achievement` (
    `id` BIGINT NOT NULL,
    `key` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) NULL,
    `icon` VARCHAR(255) NULL,
    `condition_json` JSON NOT NULL COMMENT '{"event":"deposit.completed","metric":"sum","table":"game_deposit_order","threshold":10000}',
    `points` INT NOT NULL DEFAULT 10,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_user_achievement` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `achievement_id` BIGINT NOT NULL,
    `progress` INT NOT NULL DEFAULT 0,
    `completed` TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_user_ach` (`user_id`, `achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phase 4: 社交
CREATE TABLE IF NOT EXISTS `game_friend` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `friend_id` BIGINT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/accepted/blocked',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_pair` (`user_id`, `friend_id`),
    INDEX `idx_friend` (`friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_message` (
    `id` BIGINT NOT NULL,
    `from_user_id` BIGINT NOT NULL,
    `to_user_id` BIGINT NOT NULL,
    `content` TEXT NOT NULL,
    `is_read` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_conversation` (`from_user_id`, `to_user_id`),
    INDEX `idx_to_read` (`to_user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIP 种子数据
-- ⚠ install.sql 里**没有**这 4 条（grep `INSERT ... game_vip_level` = 0）⇒ 本迁移是唯一来源。
--   消费方：VipService::getNextLevel()（packages/platform-common/src/service/VipService.php:38）、
--   VipLevelController（管理端可增删改）。
-- ⚠ `benefits` 的三个键（exchange_discount / withdraw_fee_discount / rate_bonus）与
--   VipService 现有三个 public 方法逐字对应 —— 改动这里前先核那三个方法。
INSERT IGNORE INTO `game_vip_level` (`id`, `level`, `name`, `required_exp`, `benefits`) VALUES
(20260804000100, 1, 'Silver', 500, '{"exchange_discount":"0.02","withdraw_fee_discount":"0.10","rate_bonus":"0.001"}'),
(20260804000101, 2, 'Gold', 2500, '{"exchange_discount":"0.05","withdraw_fee_discount":"0.30","rate_bonus":"0.003"}'),
(20260804000102, 3, 'Platinum', 12500, '{"exchange_discount":"0.10","withdraw_fee_discount":"0.50","rate_bonus":"0.005"}'),
(20260804000103, 4, 'Diamond', 62500, '{"exchange_discount":"0.15","withdraw_fee_discount":"1.00","rate_bonus":"0.010"}');

-- 成就种子数据
-- ⚠ install.sql 里**没有**这 12 条（grep `INSERT ... game_achievement` = 0）⇒ 本迁移是唯一来源。
--   消费方：service 的 AchievementService（按 condition_json->event 查定义并发成就）。
--   这 12 条正是 README「12 个内置成就」所指的那批 —— 在那之前它们不在任何会被执行的路径上。
INSERT IGNORE INTO `game_achievement` (`id`, `key`, `name`, `description`, `condition_json`, `points`) VALUES
(20260804000201, 'first_deposit', 'First Deposit', 'Make your first deposit', '{"event":"deposit.completed","metric":"count","table":"game_deposit_order","threshold":1}', 20),
(20260804000202, 'deposit_100', 'Century Club', 'Accumulate 100 in deposits', '{"event":"deposit.completed","metric":"sum","table":"game_deposit_order","sum_column":"platform_amount","threshold":100}', 50),
(20260804000203, 'deposit_1000', 'High Roller', 'Accumulate 1000 in deposits', '{"event":"deposit.completed","metric":"sum","table":"game_deposit_order","sum_column":"platform_amount","threshold":1000}', 100),
(20260804000204, 'first_exchange', 'Trader', 'Complete your first exchange', '{"event":"exchange.completed","metric":"count","table":"game_exchange_record","threshold":1}', 20),
(20260804000205, 'exchange_100', 'Day Trader', 'Complete 100 exchanges', '{"event":"exchange.completed","metric":"count","table":"game_exchange_record","threshold":100}', 100),
(20260804000206, 'play_3_games', 'Explorer', 'Play 3 different games', '{"event":"game.played","metric":"distinct_count","table":"game_game_play_log","distinct_column":"game_id","threshold":3}', 30),
(20260804000207, 'play_5_games', 'Adventurer', 'Play 5 different games', '{"event":"game.played","metric":"distinct_count","table":"game_game_play_log","distinct_column":"game_id","threshold":5}', 50),
(20260804000208, 'play_10_games', 'Conqueror', 'Play 10 different games', '{"event":"game.played","metric":"distinct_count","table":"game_game_play_log","distinct_column":"game_id","threshold":10}', 100),
(20260804000209, 'login_7_days', 'Weekly Warrior', 'Login 7 days in a row', '{"event":"user.login","metric":"consecutive_days","threshold":7}', 30),
(20260804000210, 'login_30_days', 'Monthly Master', 'Login 30 days in a row', '{"event":"user.login","metric":"consecutive_days","threshold":30}', 100),
(20260804000211, 'invite_1', 'Connector', 'Invite 1 friend', '{"event":"referral.applied","metric":"count","table":"game_referral","column":"referrer_id","threshold":1}', 30),
(20260804000212, 'invite_10', 'Influencer', 'Invite 10 friends', '{"event":"referral.applied","metric":"count","table":"game_referral","column":"referrer_id","threshold":10}', 100);

-- Feature flags
-- ⚠ install.sql 里 game_platform_config 自带的种子是 system/payment/withdraw/referral 那几组，
--   **不含**这 4 个 feature flag（grep 'tournament' / 'achievements' 在 install.sql = 0）⇒ 本迁移是唯一来源。
INSERT IGNORE INTO `game_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20260804000301, 'feature', 'tournament', 'off', 'string', 'Tournament system'),
(20260804000302, 'feature', 'chat', 'off', 'string', 'Chat/WebSocket messaging'),
(20260804000303, 'feature', 'vip', 'off', 'string', 'VIP loyalty system'),
(20260804000304, 'feature', 'achievements', 'off', 'string', 'Achievement/badge system');
