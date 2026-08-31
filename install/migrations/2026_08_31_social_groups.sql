-- ============================================================
-- 社交拉新（M4）迁移：组队/公会 + 分享短码 3 张表
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_social_groups.sql
-- ============================================================

-- 1. 组/公会表（type=team 组队 短时；type=guild 公会 长期；同一张表同一套 CRUD）
CREATE TABLE IF NOT EXISTS `game_group` (
    `id` BIGINT NOT NULL COMMENT '主键ID，由snowflake生成',
    `type` VARCHAR(16) NOT NULL COMMENT 'team=组队 guild=公会',
    `name` VARCHAR(100) NOT NULL COMMENT '名称',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '归属游戏(team 必填；guild 0=跨游戏)',
    `owner_id` BIGINT UNSIGNED NOT NULL COMMENT '创建人/会长',
    `level` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '公会等级；team 恒为 1',
    `xp` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '公会经验',
    `member_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '冗余计数，避免 COUNT 查询（定时校正兜底）',
    `announcement` TEXT COMMENT '公会公告；team 留空',
    `expire_at` DATETIME DEFAULT NULL COMMENT 'team 到期自动解散',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 0=解散',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_type_game` (`type`, `game_id`),
    KEY `idx_type_level` (`type`, `level` DESC),
    KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='组队/公会表';

-- 2. 成员表（uk_group_user 防并发重复入组；left_at 软删除保留历史）
CREATE TABLE IF NOT EXISTS `game_group_member` (
    `id` BIGINT NOT NULL COMMENT '主键ID，由snowflake生成',
    `group_id` BIGINT UNSIGNED NOT NULL COMMENT '组ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `role` VARCHAR(16) NOT NULL DEFAULT 'member' COMMENT 'owner/admin/member/guest',
    `contrib` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '贡献值（公会排行榜用）',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '加入时间',
    `left_at` DATETIME DEFAULT NULL COMMENT '离开时间（NULL=仍在组）',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    KEY `idx_user_role` (`user_id`, `role`),
    KEY `idx_group_contrib` (`group_id`, `contrib` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='组队/公会成员表';

-- 3. 分享短码表（裂变追踪，短生命周期，可归档）
CREATE TABLE IF NOT EXISTS `game_share_link` (
    `id` BIGINT NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '分享者用户ID',
    `activity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联 M3 活动，0=无活动',
    `short_code` VARCHAR(12) NOT NULL COMMENT '分享短码',
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击次数',
    `conversions` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击后成功注册数',
    `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_code` (`short_code`),
    KEY `idx_user` (`user_id`),
    KEY `idx_activity` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分享短码表';
