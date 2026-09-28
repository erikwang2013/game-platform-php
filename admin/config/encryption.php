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

    // ⚠ 本键当前不生效（死配置），改它不会有任何效果。
    // 算法硬编码在 EncryptionService::getInstance()：
    //   packages/platform-common/src/EncryptionService.php:32-35
    //   EncryptionManagerFactory::fromMasterKey($key, 'aes-256-cbc-hmac')
    // 该标识符对应 Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor
    // （其 identifier 即 'aes-256-cbc-hmac'，底层 OpenSSL 算法为 aes-256-cbc）。
    // 全仓唯一读取点是 EncryptionService.php:24，且只取 ['key']。
    // 要真正启用本键，须先确认库内既有密文能解密（ExportController.php:93
    // 存在活解密路径），算法一换旧密文即解不开 —— 属破坏性变更，勿轻动。
    //
    // 注意区分：死的只是 config('encryption')['cipher'] 这个「配置键」。
    // 同名环境变量 ENCRYPTION_CIPHER 在别处仍会被消费 —— encryptable 的回退配置
    // EnvEncryptableConfig::getCipher()（Encryption.php:189，无框架绑定时生效）。
    // 本仓是否走到该回退路径未逐一确认，故保守起见：勿从 .env 删除此变量。
    'cipher' => getenv('ENCRYPTION_CIPHER') ?: 'AES-256-CBC',

    // ⚠ 本键当前不生效（死配置），且不应生效：CBC 的 IV 必须每次加密随机生成。
    // OpenSslAes256CbcEncryptor::encrypt() 内部 random_bytes(IV_LEN) 取随机 IV
    // 并随密文打包，配置固定 IV 会让相同明文产出相同密文，反而削弱加密。
    // 全仓无任何读取点（唯一读者 EncryptionService.php:24 只取 ['key']）。
    'iv' => getenv('ENCRYPTION_IV') ?: '',
];
