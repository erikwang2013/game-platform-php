<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * HKDF 类密钥派生门面。
 */
final class KeyDerivationManager
{
    public function __construct(
        private readonly KeyDerivationRegistry $registry,
        private string $defaultIdentifier,
    ) {
        if (!$registry->has($defaultIdentifier)) {
            throw new EncryptionException(sprintf('Default KDF "%s" is not registered.', $defaultIdentifier));
        }
    }

    public function derive(string $ikm, string $salt, int $length, string $info = '', ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->derive($ikm, $salt, $length, $info);
    }

    public function registry(): KeyDerivationRegistry
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
            throw new EncryptionException(sprintf('KDF "%s" is not registered.', $identifier));
        }
        $this->defaultIdentifier = $identifier;
    }
}
