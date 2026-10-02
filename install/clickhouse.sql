-- ClickHouse 初始化表
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- ⚠ 本文件**只放 ClickHouse（MergeTree）DDL** —— 它的唯一文档化用法是
--   `clickhouse-client < install/clickhouse.sql`（docs/CLICKHOUSE_INSTALL.*:43，13 份）。
--
--   2026-10-02 之前，第 17 行起还寄居着一份 **MySQL** 脚本（有自己的版权头：
--   「生态扩展迁移: Phase 1-5 新增表与表结构变更」），内容是 2 条 ALTER + 10 张
--   `ENGINE=InnoDB` 的 CREATE TABLE + 18 行种子。它让上面那条命令在第 23 行
--   （`ALTER TABLE \`game_game\` ADD COLUMN ...`，MySQL 语法、且 ClickHouse 里没有这张表）
--   就会失败 —— 也就是**那份 MySQL 脚本从未被任何路径执行过**，连带
--   `game_game.provider_config` 这一列、4 条 VIP 定义、12 条成就定义、4 条 feature flag
--   全都只存在于本文件里。
--
--   该半已移出为 **`install/migrations/2026_08_27_ecosystem_expansion.sql`**
--   （由 install/Installer.php:280 的 glob 自动应用；迁移头里逐项写明了它与 install.sql
--   的取代关系、以及哪几项**只有它有**）。
--
--   **别再往本文件里加 MySQL 语句。** 判据（⚠ **必须先剥掉注释行** —— 上面这几段注释里
--   本身就写着那几个**字样**，不剥的话命令会数到注释自身、恒报「加错了」）：
--     grep -vE '^[[:space:]]*--' install/clickhouse.sql | grep -cE 'InnoDB|utf8mb4|ALTER TABLE'
--   命中非 0 才是真加错了；剥注释后应为 0。

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
