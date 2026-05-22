<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 数据库敏感字段加解密配置
 * 用于数据持久层的字段加解密，与接口传输层加密（encryption）是独立的密钥体系
 * @link https://github.com/erikwang2013/encryptable
 */
return [
    // 数据库加密密钥，生产环境请使用 32 字节随机字符串并通过环境变量注入
    'key' => getenv('ENCRYPTABLE_KEY') ?: 'open-admin-db-encryption-key-32b',

    // 加密算法，推荐 AES-256-CBC
    'cipher' => getenv('ENCRYPTABLE_CIPHER') ?: 'AES-256-CBC',
];
