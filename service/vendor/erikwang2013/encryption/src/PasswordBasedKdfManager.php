<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 口令派生密钥门面（PBKDF2 等）。
 */
final class PasswordBasedKdfManager
{
    public function __construct(
        private readonly PasswordBasedKdfRegistry $registry,
        private string $defaultIdentifier,
    ) {
        if (!$registry->has($defaultIdentifier)) {
            throw new EncryptionException(sprintf('Default password KDF "%s" is not registered.', $defaultIdentifier));
        }
    }

    public function deriveFromPassword(string $password, string $salt, int $length, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->deriveFromPassword($password, $salt, $length);
    }

    public function registry(): PasswordBasedKdfRegistry
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
            throw new EncryptionException(sprintf('Password KDF "%s" is not registered.', $identifier));
        }
        $this->defaultIdentifier = $identifier;
    }
}
