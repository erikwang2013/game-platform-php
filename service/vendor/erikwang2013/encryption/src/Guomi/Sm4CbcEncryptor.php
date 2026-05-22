<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 国密 SM4-CBC（PKCS#5/7 填充）+ HMAC-SHA256（encrypt-then-mac），依赖 OpenSSL 的 SM4-CBC 与 pohoc/crypto-sm 封装。
 * 载荷：v1 | IV(16) | MAC(32) | 密文（hex 解码后的二进制）。
 */
final class Sm4CbcEncryptor implements EncryptorInterface
{
    private const PREFIX = 'v1';
    private const MAC_LEN = 32;
    private const IV_LEN = 16;

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'sm4-cbc',
    ) {
        if (strlen($this->key) !== 16) {
            throw new EncryptionException('SM4 key must be exactly 16 bytes.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $keyHex = bin2hex($this->key);
        $ivHex = bin2hex($iv);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv($ivHex)
            ->setPadding('pkcs5');
        $hex = Sm4::encrypt($plaintext, $keyHex, $options);
        $ct = hex2bin($hex);
        if ($ct === false) {
            throw new EncryptionException('SM4 encryption failed.');
        }

        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $mac = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (strlen($mac) !== self::MAC_LEN) {
            throw new EncryptionException('SM4 HMAC generation failed.');
        }

        return self::PREFIX . $iv . $mac . $ct;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid SM4 ciphertext prefix.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < self::IV_LEN + self::MAC_LEN) {
            throw new EncryptionException('SM4 ciphertext too short.');
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $mac = substr($blob, self::IV_LEN, self::MAC_LEN);
        $ct = substr($blob, self::IV_LEN + self::MAC_LEN);
        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $expected = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (!hash_equals($expected, $mac)) {
            throw new EncryptionException('SM4 MAC verification failed.');
        }

        $keyHex = bin2hex($this->key);
        $ivHex = bin2hex($iv);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv($ivHex)
            ->setPadding('pkcs5');

        return Sm4::decrypt(bin2hex($ct), $keyHex, $options);
    }
}
