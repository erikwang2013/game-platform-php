<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\AsymmetricCipherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 非对称加解密门面：按标识选择实现（如 sm2）。
 */
final class AsymmetricCryptoManager
{
    public function __construct(
        private readonly AsymmetricCipherRegistry $registry,
        private string $defaultIdentifier,
    ) {
        if (!$registry->has($defaultIdentifier)) {
            throw new EncryptionException(sprintf('Default asymmetric cipher "%s" is not registered.', $defaultIdentifier));
        }
    }

    public function encrypt(string $plaintext, string $publicKey, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->encrypt($plaintext, $publicKey);
    }

    public function decrypt(string $ciphertext, string $privateKey, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->decrypt($ciphertext, $privateKey);
    }

    public function registry(): AsymmetricCipherRegistry
    {
        return $this->registry;
    }

    public function defaultCipher(): AsymmetricCipherInterface
    {
        return $this->registry->get($this->defaultIdentifier);
    }

    public function getDefaultIdentifier(): string
    {
        return $this->defaultIdentifier;
    }

    public function setDefaultIdentifier(string $identifier): void
    {
        if (!$this->registry->has($identifier)) {
            throw new EncryptionException(sprintf('Asymmetric cipher "%s" is not registered.', $identifier));
        }
        $this->defaultIdentifier = $identifier;
    }
}
