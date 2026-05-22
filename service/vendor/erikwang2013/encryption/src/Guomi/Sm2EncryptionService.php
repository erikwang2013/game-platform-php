<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\SM2\Keypair;
use CryptoSm\SM2\Sm2;
use CryptoSm\SM2\Sm2CipherOptions;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 国密 SM2 公钥加密 / 私钥解密（密文为十六进制字符串，默认 C1C3C2）。
 * 依赖 ext-gmp（pohoc/crypto-sm 的大数运算）。
 */
final class Sm2EncryptionService
{
    public static function requireGmp(): void
    {
        if (!extension_loaded('gmp')) {
            throw new EncryptionException('SM2 加解密需要 PHP 扩展 ext-gmp。');
        }
    }

    /**
     * @param string $publicKeyHex 非压缩公钥 04|X|Y（128 位十六进制）等，与 crypto-sm 约定一致
     */
    public static function encrypt(string $plaintext, string $publicKeyHex, ?Sm2CipherOptions $options = null): string
    {
        self::requireGmp();

        return Sm2::doEncrypt($plaintext, $publicKeyHex, $options);
    }

    /**
     * @param string $ciphertextHex doEncrypt 返回的十六进制密文
     */
    public static function decrypt(string $ciphertextHex, string $privateKeyHex, ?Sm2CipherOptions $options = null): string
    {
        self::requireGmp();

        return Sm2::doDecrypt($ciphertextHex, $privateKeyHex, $options);
    }

    /**
     * 生成十六进制公私钥对（需 ext-gmp）。
     */
    public static function generateKeyPairHex(): Keypair
    {
        self::requireGmp();

        return Sm2::generateKeyPairHex();
    }
}
