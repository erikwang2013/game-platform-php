-- ============================================================
-- 运营活动引擎迁移：3 张活动表 + game_coupon 补列
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_activity.sql
-- ============================================================

-- 1. 活动定义表（配置驱动，运营在管理端建，不发版生效）
CREATE TABLE IF NOT EXISTS `game_activity` (
    `id` BIGINT NOT NULL COMMENT '主键ID，由snowflake生成',
    `type` VARCHAR(30) NOT NULL COMMENT '活动类型: signin=签到 daily_task=每日任务',
    `name` VARCHAR(100) NOT NULL COMMENT '活动名称',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '适用游戏(0=全平台)',
    `config` JSON NOT NULL COMMENT '目标/周期/奖励，按 type 定义 schema',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=禁用 1=启用 2=已结束',
    `start_at` DATETIME DEFAULT NULL COMMENT '开始时间',
    `end_at` DATETIME DEFAULT NULL COMMENT '结束时间',
    `rollout_percent` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT '灰度百分比(0=不投放 100=全量)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_at`, `end_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运营活动定义表';

-- 2. 参与进度表（uk user+activity+period 幂等：同用户同活动同周期仅一条进度）
CREATE TABLE IF NOT EXISTS `game_activity_participation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `activity_id` BIGINT UNSIGNED NOT NULL COMMENT '活动ID',
    `period_key` VARCHAR(16) NOT NULL COMMENT '周期键: YYYY-MM-DD(每日) / all(一次性)',
    `current` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `target` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '目标值(快照，活动改配置不影响历史)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'progressing' COMMENT 'progressing=进行中 completed=已达标 rewarded=已发奖',
    `completed_at` DATETIME DEFAULT NULL COMMENT '达标时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_activity_period` (`user_id`, `activity_id`, `period_key`),
    KEY `idx_activity_status` (`activity_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活动参与进度表';

-- 3. 发奖记录表（uk participation+reward_type+reward_ref 幂等：同一进度同一类奖只发一次）
CREATE TABLE IF NOT EXISTS `game_activity_reward_log` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `activity_id` BIGINT UNSIGNED NOT NULL COMMENT '活动ID',
    `participation_id` BIGINT UNSIGNED NOT NULL COMMENT '参与进度ID',
    `period_key` VARCHAR(16) NOT NULL COMMENT '周期键',
    `reward_type` VARCHAR(20) NOT NULL COMMENT '奖励类型: platform_coin=平台币 game_coin=游戏币',
    `reward_ref` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '奖励条目索引(1起)/游戏ID等',
    `amount` DECIMAL(20,8) NOT NULL DEFAULT 0 COMMENT '奖励数量',
    `status` VARCHAR(20) NOT NULL DEFAULT 'succeeded' COMMENT 'succeeded=已发放',
    `fail_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '失败原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_idempotent` (`participation_id`, `reward_type`, `reward_ref`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活动发奖记录表';

-- 4. 顺带修：game_coupon 补 conditions 列（模型已声明 fillable，表缺列）
-- 注意：仅对旧库执行一次；MySQL 8 不支持 ADD COLUMN IF NOT EXISTS，重复执行会报 Duplicate column
ALTER TABLE `game_coupon` ADD COLUMN `conditions` JSON NULL COMMENT '使用条件(JSON)' AFTER `max_discount`;
