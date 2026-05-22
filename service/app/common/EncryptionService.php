<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;

/**
 * API 敏感数据加解密服务
 * 用于接口传输层的敏感字段加解密
 */
class EncryptionService
{
    private static ?EncryptionManager $instance = null;

    private static function getInstance(): EncryptionManager
    {
        if (self::$instance === null) {
            $config = config('encryption', []);
            $key = $config['key'] ?? 'open-admin-api-encryption-key32b';

            // EncryptionManagerFactory 要求主密钥恰好 32 字节
            if (strlen($key) !== 32) {
                $key = str_pad(substr($key, 0, 32), 32, "\0");
            }

            self::$instance = EncryptionManagerFactory::fromMasterKey(
                $key,
                'aes-256-cbc-hmac'
            );
        }
        return self::$instance;
    }

    public static function encrypt(string $value): string
    {
        if (empty($value)) return '';
        return self::getInstance()->encrypt($value);
    }

    public static function decrypt(string $value): string
    {
        if (empty($value)) return '';
        return self::getInstance()->decrypt($value);
    }

    /**
     * 手机号脱敏: 138****1234
     */
    public static function maskPhone(string $phone): string
    {
        if (mb_strlen($phone) < 7) return $phone;
        return mb_substr($phone, 0, 3) . '****' . mb_substr($phone, -4);
    }

    /**
     * 邮箱脱敏: a***@example.com
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $name = $parts[0];
        return (mb_strlen($name) > 2 ? $name[0] . '***' : $name[0] . '**') . '@' . $parts[1];
    }
}
