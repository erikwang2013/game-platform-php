<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * Snowflake ID 生成器配置
 * 用于生成全局唯一 BIGINT 主键，分布式环境需为每个节点分配不同 datacenter_id 和 worker_id
 * @link https://github.com/erikwang2013/snowflake-php
 */
return [
    // 数据中心 ID，取值范围 0-31。单机房部署保持默认值即可
    'datacenter_id' => (int)(getenv('SNOWFLAKE_DATACENTER_ID') ?: 1),

    // 工作节点 ID，取值范围 0-31。同一数据中心内每台机器需分配不同编号
    'worker_id' => (int)(getenv('SNOWFLAKE_WORKER_ID') ?: 1),

    // 起始时间戳（毫秒），用于压缩 ID 长度。修改此值会导致已生成 ID 失效
    'start_timestamp' => (int)(getenv('SNOWFLAKE_START_TIMESTAMP') ?: 1700000000000),
];
