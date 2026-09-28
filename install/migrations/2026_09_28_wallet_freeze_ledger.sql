-- ============================================================
-- 钱包冻结子台账（per-hold）：frozen_balance 从「单池列」改为「聚合缓存 + 台账为权威」
-- 2026-09-28
-- 对已部署数据库执行（install.sql 已包含等价定义，仅对新装有效）
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_09_28_wallet_freeze_ledger.sql
--
-- 不变量（WalletService::assertFreezeLedger() 在事务内断言，分叉即回滚该笔资金操作）：
--     同钱包 Σ(game_wallet_hold.remaining) == user_wallet.frozen_balance
--
-- ⚠ 执行顺序：**先跑本迁移，再上带子台账的代码**。反过来（代码先上、迁移没跑）时，
--    任何「钱包有冻结、台账一笔都没有」的账户上 lock/unlock 都会 fail-closed 抛异常
--    （这是有意的：宁可拒绝，也不写出一笔事后谁都对不上的冻结）。抛错信息里带本文件名。
--
-- 本迁移做四件事（全部在一个事务里，除建表）：
--   1. 建表
--   2. 回填：把既有 lock/unlock 流水反推成 hold 行（FIFO 口径，见下）
--   3. 损伤修复（用户已裁定）：流水口径的应冻结额 > 冻结列 ⇒ 差额**补进** frozen_balance，
--      并写 type=reconcile 的可审计流水（不是裸 UPDATE，也不是删记录）
--   4. 核不上账的差额（冻结列 > 台账口径）**不编造金额**：不搬一分钱，落一行标注来源的 hold
--      （ref_type='backfill_unmatched'）把不变量补齐，待人工核对
-- ============================================================

-- ============================================================
-- 1. 冻结子台账
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_wallet_hold` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成（回填行沿用来源流水/钱包行的雪花ID）',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `scope` VARCHAR(20) NOT NULL DEFAULT 'platform' COMMENT '钱包范围: platform=平台币/game=游戏币',
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '游戏ID（scope=game 时有效）',
    `currency_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '币种ID（scope=game 时有效）',
    `amount` DECIMAL(20,8) UNSIGNED NOT NULL COMMENT '本次冻结的原始金额',
    `remaining` DECIMAL(20,8) UNSIGNED NOT NULL COMMENT '尚未释放的份额（不变量：同钱包 Σremaining == 钱包 frozen_balance）',
    `ref_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '冻结来源单据类型（回填且来源不明时=backfill_unmatched）',
    `ref_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '冻结来源单据ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=冻结中 2=已释放(remaining 归零)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '冻结时间',
    `released_at` DATETIME NULL DEFAULT NULL COMMENT '释放完成时间（部分释放、以及回填行保持 NULL：历史释放时刻无法归属到笔，不编造）',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_wallet` (`user_id`, `scope`, `game_id`, `currency_id`, `id`),
    KEY `idx_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包冻结子台账（per-hold，frozen_balance 的权威来源）';

START TRANSACTION;

-- ============================================================
-- 2. 回填：一笔 type=lock 流水 ⇒ 一行 hold
--    消费口径 FIFO：把该钱包（user_id/scope/game_id/currency_id）的 Σunlock 从最老的 hold 往前吃。
--      cum_i        = 该 hold 之前的累计冻结额（含自己）
--      remaining_i  = LEAST(amount_i, GREATEST(cum_i − Σunlock, 0))
--    id 直接沿用 lock 流水的雪花主键：它本来就全局唯一、非自增（同库所有表共用一个雪花序列），
--    且让「哪一行 hold 来自哪一笔流水」可反查；重跑由 WHERE NOT EXISTS 反连接挡掉，天然幂等。
--    全部回填行的 released_at 留 NULL：历史释放的确切时刻归属不到笔，不编造时间。
-- ============================================================
INSERT INTO `game_wallet_hold`
    (`id`, `user_id`, `scope`, `game_id`, `currency_id`, `amount`, `remaining`,
     `ref_type`, `ref_id`, `status`, `created_at`, `released_at`)
SELECT
    l.`id`,
    l.`user_id`,
    l.`scope`,
    l.`game_id`,
    l.`currency_id`,
    l.`amt`,
    LEAST(l.`amt`, GREATEST(l.`cum` - COALESCE(u.`unlocked`, 0), 0)),
    l.`ref_type`,
    l.`ref_id`,
    IF(l.`cum` <= COALESCE(u.`unlocked`, 0), 2, 1),
    l.`created_at`,
    NULL
FROM (
    SELECT
        t.`id`, t.`user_id`, t.`scope`, t.`game_id`, t.`currency_id`,
        -t.`amount` AS `amt`, t.`ref_type`, t.`ref_id`, t.`created_at`,
        SUM(-t.`amount`) OVER (
            PARTITION BY t.`user_id`, t.`scope`, t.`game_id`, t.`currency_id`
            ORDER BY t.`id`
        ) AS `cum`
    FROM `game_transaction` t
    WHERE t.`type` = 'lock'
) l
LEFT JOIN (
    SELECT `user_id`, `scope`, `game_id`, `currency_id`, SUM(`amount`) AS `unlocked`
    FROM `game_transaction`
    WHERE `type` = 'unlock'
    GROUP BY `user_id`, `scope`, `game_id`, `currency_id`
) u
    ON  u.`user_id`     = l.`user_id`
    AND u.`scope`       = l.`scope`
    AND u.`game_id`     = l.`game_id`
    AND u.`currency_id` = l.`currency_id`
WHERE NOT EXISTS (SELECT 1 FROM `game_wallet_hold` `h` WHERE h.`id` = l.`id`);

-- ============================================================
-- 3. 损伤修复：Σremaining > 冻结列 ⇒ 差额补进 frozen_balance + reconcile 流水
--
--    成因（已核代码，非推断）：修复前的 lock() 传 $fromFrozen=false ⇒ apply() 里
--    frozenAfter 原样不动、balanceAfter 照减 ⇒ 可用余额被扣、冻结列没涨，钱被吞掉。
--    既有库上表现为「有 lock 流水、frozen_balance 却偏低」。
--
--    方向恒为「补进」：本迁移只上调 frozen_balance，**绝不下调** —— 下调等于凭空销毁冻结资产。
--    流水行的 amount 记 0：本次修正确实没有动可用余额（amount 在 game_transaction 里恒为
--    「可用余额的变动」），差额写在 remark 里可审；逐笔份额在 game_wallet_hold 上。
--    id 沿用钱包行的雪花主键（同样全局唯一、非自增）；重跑靠 WHERE 条件自然 0 行，不写
--    ON DUPLICATE KEY 空操作——真撞主键说明状态异常，应当报错而不是静默跳过这笔修复。
-- ============================================================
INSERT INTO `game_transaction`
    (`id`, `user_id`, `type`, `scope`, `game_id`, `currency_id`, `amount`, `balance_after`,
     `ref_type`, `ref_id`, `remark`, `created_at`)
SELECT
    w.`id`, w.`user_id`, 'reconcile', 'platform', 0, 0, 0.00000000, w.`balance`,
    'freeze_backfill', 0,
    CONCAT('冻结台账回填：旧 lock() 吞掉可用余额的差额 ',
           CAST(s.`held` - w.`frozen_balance` AS CHAR), ' 补入 frozen_balance（逐笔见 game_wallet_hold）'),
    NOW()
FROM `game_user_wallet` w
JOIN (
    SELECT `user_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'platform'
    GROUP BY `user_id`
) s ON s.`user_id` = w.`user_id`
WHERE w.`frozen_balance` < s.`held`;

UPDATE `game_user_wallet` w
JOIN (
    SELECT `user_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'platform'
    GROUP BY `user_id`
) s ON s.`user_id` = w.`user_id`
SET w.`frozen_balance` = s.`held`
WHERE w.`frozen_balance` < s.`held`;

-- 游戏币钱包同形（今天全仓唯一冻结入口是管理端风控 hold，恒为 platform ⇒ 这两段通常 0 行；
-- 留着是因为不变量按钱包行定义，半覆盖的不变量比不覆盖更危险）。
INSERT INTO `game_transaction`
    (`id`, `user_id`, `type`, `scope`, `game_id`, `currency_id`, `amount`, `balance_after`,
     `ref_type`, `ref_id`, `remark`, `created_at`)
SELECT
    w.`id`, w.`user_id`, 'reconcile', 'game', w.`game_id`, w.`currency_id`, 0.00000000, w.`balance`,
    'freeze_backfill', 0,
    CONCAT('冻结台账回填：旧 lock() 吞掉可用余额的差额 ',
           CAST(s.`held` - w.`frozen_balance` AS CHAR), ' 补入 frozen_balance（逐笔见 game_wallet_hold）'),
    NOW()
FROM `game_user_game_wallet` w
JOIN (
    SELECT `user_id`, `game_id`, `currency_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'game'
    GROUP BY `user_id`, `game_id`, `currency_id`
) s ON  s.`user_id`     = w.`user_id`
    AND s.`game_id`     = w.`game_id`
    AND s.`currency_id` = w.`currency_id`
WHERE w.`frozen_balance` < s.`held`;

UPDATE `game_user_game_wallet` w
JOIN (
    SELECT `user_id`, `game_id`, `currency_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'game'
    GROUP BY `user_id`, `game_id`, `currency_id`
) s ON  s.`user_id`     = w.`user_id`
    AND s.`game_id`     = w.`game_id`
    AND s.`currency_id` = w.`currency_id`
SET w.`frozen_balance` = s.`held`
WHERE w.`frozen_balance` < s.`held`;

-- ============================================================
-- 4. 核不上账的差额：冻结列 > 台账口径（有冻结却没有任何 lock 流水可解释它）
--
--    不编造金额、不搬一分钱：既不把差额从 frozen_balance 抹掉（那是销毁用户资产），
--    也不并进 available（那是铸币）。落一行标注来源的 hold 把不变量补齐，等人工核对：
--        SELECT * FROM game_wallet_hold WHERE ref_type = 'backfill_unmatched';
--    这行的存在本身就是「该钱包的冻结缺台账依据」的标记。
-- ============================================================
INSERT INTO `game_wallet_hold`
    (`id`, `user_id`, `scope`, `game_id`, `currency_id`, `amount`, `remaining`,
     `ref_type`, `ref_id`, `status`, `created_at`, `released_at`)
SELECT
    w.`id`, w.`user_id`, 'platform', 0, 0,
    w.`frozen_balance` - COALESCE(s.`held`, 0),
    w.`frozen_balance` - COALESCE(s.`held`, 0),
    'backfill_unmatched', 0, 1, NOW(), NULL
FROM `game_user_wallet` w
LEFT JOIN (
    SELECT `user_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'platform'
    GROUP BY `user_id`
) s ON s.`user_id` = w.`user_id`
WHERE w.`frozen_balance` > COALESCE(s.`held`, 0);

INSERT INTO `game_wallet_hold`
    (`id`, `user_id`, `scope`, `game_id`, `currency_id`, `amount`, `remaining`,
     `ref_type`, `ref_id`, `status`, `created_at`, `released_at`)
SELECT
    w.`id`, w.`user_id`, 'game', w.`game_id`, w.`currency_id`,
    w.`frozen_balance` - COALESCE(s.`held`, 0),
    w.`frozen_balance` - COALESCE(s.`held`, 0),
    'backfill_unmatched', 0, 1, NOW(), NULL
FROM `game_user_game_wallet` w
LEFT JOIN (
    SELECT `user_id`, `game_id`, `currency_id`, SUM(`remaining`) AS `held`
    FROM `game_wallet_hold`
    WHERE `scope` = 'game'
    GROUP BY `user_id`, `game_id`, `currency_id`
) s ON  s.`user_id`     = w.`user_id`
    AND s.`game_id`     = w.`game_id`
    AND s.`currency_id` = w.`currency_id`
WHERE w.`frozen_balance` > COALESCE(s.`held`, 0);

COMMIT;

-- ============================================================
-- 验收：两段都必须返回 0 行（不变量成立）。返回非 0 行 = 迁移失败，回滚重来。
-- ============================================================
-- SELECT w.user_id, w.frozen_balance, COALESCE(s.held, 0) AS held
-- FROM `game_user_wallet` w
-- LEFT JOIN (SELECT user_id, SUM(remaining) AS held FROM `game_wallet_hold`
--            WHERE scope='platform' GROUP BY user_id) s ON s.user_id = w.user_id
-- WHERE w.frozen_balance <> COALESCE(s.held, 0);
--
-- SELECT w.user_id, w.game_id, w.currency_id, w.frozen_balance, COALESCE(s.held, 0) AS held
-- FROM `game_user_game_wallet` w
-- LEFT JOIN (SELECT user_id, game_id, currency_id, SUM(remaining) AS held FROM `game_wallet_hold`
--            WHERE scope='game' GROUP BY user_id, game_id, currency_id) s
--        ON s.user_id = w.user_id AND s.game_id = w.game_id AND s.currency_id = w.currency_id
-- WHERE w.frozen_balance <> COALESCE(s.held, 0);
