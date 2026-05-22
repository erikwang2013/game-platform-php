<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Contract;

/**
 * 对称加密：同一实例绑定固定密钥，对二进制载荷加解密。
 */
interface SymmetricCipherInterface
{
    public function getIdentifier(): string;

    /**
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function encrypt(string $plaintext): string;

    /**
     * @throws \Erikwang2013\Encryption\Exception\EncryptionException
     */
    public function decrypt(string $ciphertext): string;
}
