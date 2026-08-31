-- ============================================================
-- 充值订单留存下单用户 IP 迁移
-- 2026-08-31
-- 回调风控（PaymentController::callback）优先按订单留存 IP 做 velocity 聚合，
-- 避免网关回源 IP 汇聚多用户造成误伤。install.sql 已包含等价定义，仅对新装有效。
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_deposit_client_ip.sql
-- ============================================================

ALTER TABLE `game_deposit_order`
    ADD COLUMN `client_ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '下单用户IP（回调风控优先取此值，缺省回落网关IP）' AFTER `paid_at`;
