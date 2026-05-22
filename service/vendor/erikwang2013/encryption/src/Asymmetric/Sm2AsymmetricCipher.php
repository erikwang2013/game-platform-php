<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Asymmetric;

use CryptoSm\Exception\CryptoException;
use CryptoSm\Exception\InvalidKeyException;
use CryptoSm\SM2\Sm2CipherOptions;
use Erikwang2013\Encryption\Contract\AsymmetricCipherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;

/**
 * SM2 公钥加密 / 私钥解密，实现 {@see AsymmetricCipherInterface}。
 * 密钥与密文均为十六进制字符串（与 pohoc/crypto-sm 一致）；需 ext-gmp。
 */
final class Sm2AsymmetricCipher implements AsymmetricCipherInterface
{
    public function __construct(
        private readonly ?Sm2CipherOptions $cipherOptions = null,
        private readonly string $identifier = 'sm2',
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext, string $publicKey): string
    {
        try {
            return Sm2EncryptionService::encrypt($plaintext, $publicKey, $this->cipherOptions);
        } catch (EncryptionException $e) {
            throw $e;
        } catch (InvalidKeyException | CryptoException $e) {
            throw new EncryptionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function decrypt(string $ciphertext, string $privateKey): string
    {
        try {
            return Sm2EncryptionService::decrypt($ciphertext, $privateKey, $this->cipherOptions);
        } catch (EncryptionException $e) {
            throw $e;
        } catch (InvalidKeyException | CryptoException $e) {
            throw new EncryptionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
