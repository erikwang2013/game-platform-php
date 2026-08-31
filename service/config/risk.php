<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [
    // 设备指纹盐值：用于 sha256 指纹派生。
    // 必须与存储层加密密钥（ENCRYPTABLE_KEY）独立，禁止复用。
    // 变更后所有历史指纹失效（等价于全量重置设备档案）。
    'fingerprint_salt' => getenv('FINGERPRINT_SALT') ?: '',

    // 设备指纹评估器的 COW 上界：同设备账号数超过该值时跳过边表写放大
    'max_accounts_per_device' => 50,

    // 风控整体时间预算（毫秒），超时后跳过剩余软规则（fail-open）
    'budget_ms' => 200,
];
