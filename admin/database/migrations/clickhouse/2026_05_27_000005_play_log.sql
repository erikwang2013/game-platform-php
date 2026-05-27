-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 游戏行为日志 ClickHouse 表
-- 引擎: MergeTree，按月分区，按用户+时间排序
-- 用途: 替代 MySQL 存全量日志，支撑 OLAP 分析和概率计算
-- ============================================================

CREATE TABLE IF NOT EXISTS erik_game_play_log
(
    id          UInt64,
    user_id     UInt64,
    game_id     UInt64,
    action      String,
    detail      String DEFAULT '{}',
    ip_address  String DEFAULT '',
    user_agent  String DEFAULT '',
    created_at  DateTime DEFAULT now(),
    updated_at  DateTime DEFAULT now()
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_at)
ORDER BY (user_id, created_at)
SETTINGS index_granularity = 8192;

-- 物化视图：按游戏+动作的每小时聚合
CREATE TABLE IF NOT EXISTS erik_game_play_log_hourly
(
    game_id    UInt64,
    action     String,
    hour       DateTime,
    cnt        UInt64
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (game_id, action, hour)
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS erik_game_play_log_hourly_mv
TO erik_game_play_log_hourly
AS
SELECT
    game_id,
    action,
    toStartOfHour(created_at) AS hour,
    count() AS cnt
FROM erik_game_play_log
GROUP BY game_id, action, hour;
