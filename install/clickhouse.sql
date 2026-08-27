-- ClickHouse 初始化表
-- Copyright (c) 2026 erik <erik@erik.xyz>

CREATE TABLE IF NOT EXISTS game_game_play_log (
    id UInt64, user_id UInt64, game_id UInt64,
    action String, detail String DEFAULT '{}',
    ip_address String DEFAULT '', user_agent String DEFAULT '',
    created_at DateTime DEFAULT now(), updated_at DateTime DEFAULT now()
) ENGINE = MergeTree() PARTITION BY toYYYYMM(created_at) ORDER BY (user_id, created_at);

CREATE TABLE IF NOT EXISTS game_deposit_log (
    id UInt64, order_id UInt64, user_id UInt64,
    amount String, currency String DEFAULT 'USD',
    status String DEFAULT 'pending', payment_method String DEFAULT '',
    created_at DateTime DEFAULT now()
) ENGINE = MergeTree() PARTITION BY toYYYYMM(created_at) ORDER BY (user_id, created_at, status);
-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 生态扩展迁移: Phase 1-5 新增表与表结构变更
-- ============================================================

-- Phase 1: 表结构变更
ALTER TABLE `game_game` ADD COLUMN `provider_config` JSON NULL AFTER `sort`;

ALTER TABLE `game_game_play_log`
    ADD COLUMN `round_id` VARCHAR(64) NULL AFTER `session_id`,
    ADD COLUMN `bet_amount` DECIMAL(18,8) NULL AFTER `game_amount_after`,
    ADD COLUMN `win_amount` DECIMAL(18,8) NULL AFTER `bet_amount`;

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
INSERT INTO `game_vip_level` (`id`, `level`, `name`, `required_exp`, `benefits`) VALUES
(20260804000100, 1, 'Silver', 500, '{"exchange_discount":"0.02","withdraw_fee_discount":"0.10","rate_bonus":"0.001"}'),
(20260804000101, 2, 'Gold', 2500, '{"exchange_discount":"0.05","withdraw_fee_discount":"0.30","rate_bonus":"0.003"}'),
(20260804000102, 3, 'Platinum', 12500, '{"exchange_discount":"0.10","withdraw_fee_discount":"0.50","rate_bonus":"0.005"}'),
(20260804000103, 4, 'Diamond', 62500, '{"exchange_discount":"0.15","withdraw_fee_discount":"1.00","rate_bonus":"0.010"}');

-- 成就种子数据
INSERT INTO `game_achievement` (`id`, `key`, `name`, `description`, `condition_json`, `points`) VALUES
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
INSERT INTO `game_platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20260804000301, 'feature', 'tournament', 'off', 'string', 'Tournament system'),
(20260804000302, 'feature', 'chat', 'off', 'string', 'Chat/WebSocket messaging'),
(20260804000303, 'feature', 'vip', 'off', 'string', 'VIP loyalty system'),
(20260804000304, 'feature', 'achievements', 'off', 'string', 'Achievement/badge system');
