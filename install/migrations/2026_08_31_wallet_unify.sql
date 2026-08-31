-- ============================================================
-- 钱包统一迁移（M1）：流水表 scope 列 / 精度提升至 DECIMAL(20,8)
-- 2026-08-31
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_wallet_unify.sql
-- 注意: DECIMAL 变更前建议先执行 ALTER 校验（大表耗时），可分批/在维护窗口执行
-- ============================================================

-- 1. 平台流水表：scope/game_id/currency_id 列 + 精度提升 + 类型枚举扩充
ALTER TABLE `game_transaction`
    ADD COLUMN `scope` VARCHAR(20) NOT NULL DEFAULT 'platform' COMMENT '钱包范围: platform=平台币/game=游戏币' AFTER `type`,
    ADD COLUMN `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '游戏ID（scope=game 时有效）' AFTER `scope`,
    ADD COLUMN `currency_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '币种ID（scope=game 时有效）' AFTER `game_id`,
    MODIFY COLUMN `type` VARCHAR(20) NOT NULL COMMENT '流水类型: deposit/withdraw/exchange_in/exchange_out/game_earn/game_spend/lock/unlock/reconcile',
    MODIFY COLUMN `amount` DECIMAL(20,8) NOT NULL COMMENT '变动金额（正=收入，负=支出）',
    MODIFY COLUMN `balance_after` DECIMAL(20,8) NOT NULL COMMENT '变动后余额',
    ADD KEY `idx_user_scope` (`user_id`, `scope`);

-- 2. 平台币钱包表：精度提升至 DECIMAL(20,8)
ALTER TABLE `game_user_wallet`
    MODIFY COLUMN `balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '可用余额（平台币）',
    MODIFY COLUMN `frozen_balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '冻结余额（提现中）',
    MODIFY COLUMN `total_earned` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '累计收入（平台币）',
    MODIFY COLUMN `total_spent` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '累计支出（平台币）';

-- 3. 游戏币钱包表：精度提升至 DECIMAL(20,8)
ALTER TABLE `game_user_game_wallet`
    MODIFY COLUMN `balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '游戏币余额',
    MODIFY COLUMN `frozen_balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000 COMMENT '冻结游戏币余额';
