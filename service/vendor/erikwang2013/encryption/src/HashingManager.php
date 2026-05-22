<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\HasherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 杂凑门面：默认算法 + 按标识切换。
 */
final class HashingManager
{
    public function __construct(
        private readonly HasherRegistry $registry,
        private string $defaultIdentifier,
    ) {
        if (!$registry->has($defaultIdentifier)) {
            throw new EncryptionException(sprintf('Default hasher "%s" is not registered.', $defaultIdentifier));
        }
    }

    public function digest(string $data, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->digest($data);
    }

    public function digestHex(string $data, ?string $identifier = null): string
    {
        $id = $identifier ?? $this->defaultIdentifier;

        return $this->registry->get($id)->digestHex($data);
    }

    public function registry(): HasherRegistry
    {
        return $this->registry;
    }

    public function defaultHasher(): HasherInterface
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
            throw new EncryptionException(sprintf('Hasher "%s" is not registered.', $identifier));
        }
        $this->defaultIdentifier = $identifier;
    }
}
