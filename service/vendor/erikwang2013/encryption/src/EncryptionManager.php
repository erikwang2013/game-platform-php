<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 门面：绑定默认算法，统一加解密入口；底层从注册表解析。
 */
final class EncryptionManager
{
    public function __construct(
        private readonly EncryptorRegistry $registry,
        private string $defaultIdentifier,
    ) {
        if (!$registry->has($defaultIdentifier)) {
            throw new EncryptionException(sprintf(
                'Default encryptor "%s" is not registered.',
                $defaultIdentifier
            ));
        }
    }

    public function defaultEncryptor(): EncryptorInterface
    {
        return $this->registry->get($this->defaultIdentifier);
    }

    public function encrypt(string $plaintext, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->encrypt($plaintext);
    }

    public function decrypt(string $ciphertext, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->decrypt($ciphertext);
    }

    public function registry(): EncryptorRegistry
    {
        return $this->registry;
    }

    public function getDefaultIdentifier(): string
    {
        return $this->defaultIdentifier;
    }

    public function setDefaultIdentifier(string $identifier): void
    {
        if (!$this->registry->has($identifier)) {
            throw new EncryptionException(sprintf('Encryptor "%s" is not registered.', $identifier));
        }
        $this->defaultIdentifier = $identifier;
    }
}
