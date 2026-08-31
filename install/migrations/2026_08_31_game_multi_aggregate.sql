-- ============================================================
-- 多游戏聚合（M5）迁移：game_game 扩展类型与多端/多区域字段
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_game_multi_aggregate.sql
-- ============================================================

-- 1. type 扩展 embedded（内嵌小游戏，资金路径与 self 一致）
ALTER TABLE `game_game`
    MODIFY `type` VARCHAR(20) NOT NULL DEFAULT 'self' COMMENT '游戏类型: self=自研 embedded=内嵌 third_party=第三方';

-- 2. 新增 SDK 版本/客户端平台/运营区域
ALTER TABLE `game_game`
    ADD COLUMN `sdk_version` VARCHAR(20) DEFAULT NULL COMMENT 'SDK版本号(自研/内嵌游戏)' AFTER `api_secret`,
    ADD COLUMN `platform` VARCHAR(20) NOT NULL DEFAULT 'h5' COMMENT '客户端平台: h5/unity/web/native' AFTER `sdk_version`,
    ADD COLUMN `region` VARCHAR(10) NOT NULL DEFAULT 'global' COMMENT '运营区域: global/CN/US/EU/...' AFTER `platform`;
