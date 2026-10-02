-- ============================================================
-- game_user / game_admin_user 的 last_login_at 补索引
-- 2026-10-02
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_10_02_last_login_at_index.sql
--
-- 为什么要这两条索引：两张表的 last_login_at 此前**一条 KEY 都没有**
-- （game_user: PRIMARY / uk_username / idx_status / idx_country / idx_deleted_at / idx_created_at；
--   game_admin_user: PRIMARY / uk_username / idx_status / idx_deleted_at / idx_created_at），
-- 而它正好被四条**范围条件**查，都是进后台就会跑的路径：
--   - service/app/api/v1/controller/PlatformStatsController.php  ::stats()    的 active_users_7d
--       User::where('last_login_at', '>=', 7天前)->count()
--   - admin/app/admin/v1/controller/DashboardController.php      ::platform() 的 active_users_7d
--   - admin/app/admin/v1/controller/DashboardController.php      ::getStats() 的当日活跃（半开区间）
--   - admin/app/admin/v1/controller/MetricsController.php        ::index()    的当日活跃（whereBetween）
-- 范围条件落在无索引列上只能全表扫，用户表越大越慢（DashboardController::platform 一条缓存都没有）。
--
-- 只增不改：两条都是**新增的二级索引**，不触碰任何现有索引、列与数据。
-- 本迁移**可重复执行**：MySQL 8 没有 ADD INDEX IF NOT EXISTS，故先用 information_schema.STATISTICS
-- 判在不在再决定是否 ALTER（重复跑只打印一句跳过，不报 Duplicate key name）。
-- ============================================================

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_user'
      AND INDEX_NAME = 'idx_last_login_at'
);

SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `game_user` ADD KEY `idx_last_login_at` (`last_login_at`)',
    'SELECT ''game_user.idx_last_login_at 已存在，跳过'' AS skipped'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_admin_user'
      AND INDEX_NAME = 'idx_last_login_at'
);

SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `game_admin_user` ADD KEY `idx_last_login_at` (`last_login_at`)',
    'SELECT ''game_admin_user.idx_last_login_at 已存在，跳过'' AS skipped'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
