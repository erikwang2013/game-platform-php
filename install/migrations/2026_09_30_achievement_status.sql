-- ============================================================
-- 成就定义加 status（管理端要能上架/下架一条成就）
-- 2026-09-30
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_09_30_achievement_status.sql
--
-- 为什么要这一列：成就有真实消费方——service 的 AchievementService 按事件
-- （condition_json->event）查定义并发成就。没有 status 时，「停用一条成就」
-- 只能靠删除，而删除会连带 game_user_achievement 的历史进度失去定义。
--
-- 默认 1（启用）⇒ 存量数据行为不变。消费方（AchievementService）加 status 过滤后，
-- 关掉的成就不会再被事件触发授予，但既有用户进度与已授予记录不受影响。
--
-- ⚠ 执行顺序：**先跑本迁移，再上带 status 过滤的代码**。反过来时，
--    AchievementService 的 where('status', 1) 会因列不存在抛错，而该调用点的异常会被
--    EventConsumer 收进 $failures 驱动 Outbox 重试直至死信 —— 即未迁移就把事件毒化。
--
-- 本迁移**可重复执行**：MySQL 8 没有 ADD COLUMN IF NOT EXISTS，故用 information_schema
-- 判在不在再决定是否 ALTER（重复跑只会打印一句跳过，不报 Duplicate column）。
-- ============================================================

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_achievement'
      AND COLUMN_NAME = 'status'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `game_achievement` ADD COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''状态: 0=停用 1=启用'' AFTER `points`',
    'SELECT ''game_achievement.status 已存在，跳过'' AS skipped'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
