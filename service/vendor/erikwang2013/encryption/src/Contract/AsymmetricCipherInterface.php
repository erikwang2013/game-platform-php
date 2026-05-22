<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 非对称加解密：每次调用传入公钥/私钥材料（格式由实现约定，如 SM2 十六进制、RSA PEM）。
 */
interface AsymmetricCipherInterface
{
    public function getIdentifier(): string;

    /**
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function encrypt(string $plaintext, string $publicKey): string;

    /**
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function decrypt(string $ciphertext, string $privateKey): string;
}
