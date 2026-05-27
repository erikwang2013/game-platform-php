-- ClickHouse 初始化表
-- Copyright (c) 2026 erik <erik@erik.xyz>

CREATE TABLE IF NOT EXISTS erik_game_play_log (
    id UInt64, user_id UInt64, game_id UInt64,
    action String, detail String DEFAULT '{}',
    ip_address String DEFAULT '', user_agent String DEFAULT '',
    created_at DateTime DEFAULT now(), updated_at DateTime DEFAULT now()
) ENGINE = MergeTree() PARTITION BY toYYYYMM(created_at) ORDER BY (user_id, created_at);

CREATE TABLE IF NOT EXISTS erik_deposit_log (
    id UInt64, order_id UInt64, user_id UInt64,
    amount String, currency String DEFAULT 'USD',
    status String DEFAULT 'pending', payment_method String DEFAULT '',
    created_at DateTime DEFAULT now()
) ENGINE = MergeTree() PARTITION BY toYYYYMM(created_at) ORDER BY (user_id, created_at, status);
