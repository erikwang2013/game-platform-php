-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 充值订单 + 交易流水 ClickHouse 表
-- 用途: 支撑收入分析、充值转化率、用户价值分层
-- ============================================================

CREATE TABLE IF NOT EXISTS erik_deposit_log
(
    id              UInt64,
    order_id        UInt64,
    user_id         UInt64,
    amount          String,
    currency        String DEFAULT 'USD',
    status          String DEFAULT 'pending',
    payment_method  String DEFAULT '',
    created_at      DateTime DEFAULT now()
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_at)
ORDER BY (user_id, created_at, status)
SETTINGS index_granularity = 8192;

CREATE TABLE IF NOT EXISTS erik_transaction_log
(
    id            UInt64,
    tx_id         UInt64,
    user_id       UInt64,
    type          String,
    amount        String,
    balance_after String DEFAULT '0.0000',
    ref_type      String DEFAULT '',
    ref_id        UInt64 DEFAULT 0,
    created_at    DateTime DEFAULT now()
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_at)
ORDER BY (user_id, created_at, type)
SETTINGS index_granularity = 8192;
