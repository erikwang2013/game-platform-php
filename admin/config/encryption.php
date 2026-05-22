<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * API 敏感数据加解密配置
 * 用于接口传输层的数据加解密，与数据库存储层加密（encryptable）是独立的密钥体系
 * @link https://github.com/erikwang2013/encryption
 */
return [
    // AES 加密密钥，生产环境请使用 32 字节随机字符串并通过环境变量注入
    'key' => getenv('ENCRYPTION_KEY') ?: 'open-admin-api-encryption-key32b',

    // 加密算法，推荐 AES-256-CBC。也支持 sm4-ecb/sm4-cbc（国密）
    'cipher' => getenv('ENCRYPTION_CIPHER') ?: 'AES-256-CBC',

    // 初始化向量（IV），CBC 模式需要 16 字节。留空则使用内置默认值
    'iv' => getenv('ENCRYPTION_IV') ?: '',
];
