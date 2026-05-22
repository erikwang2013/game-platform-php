<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\PasswordBasedKdfInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 基于口令的 KDF（如 PBKDF2）注册表。
 */
final class PasswordBasedKdfRegistry
{
    /** @var array<string, PasswordBasedKdfInterface> */
    private array $kdfs = [];

    public function __construct(PasswordBasedKdfInterface ...$kdfs)
    {
        foreach ($kdfs as $k) {
            $this->register($k);
        }
    }

    public function register(PasswordBasedKdfInterface $kdf): self
    {
        $id = $kdf->getIdentifier();
        if ($id === '') {
            throw new EncryptionException('Password KDF identifier must not be empty.');
        }
        $this->kdfs[$id] = $kdf;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->kdfs[$identifier]);
    }

    public function get(string $identifier): PasswordBasedKdfInterface
    {
        if (!isset($this->kdfs[$identifier])) {
            throw new EncryptionException(sprintf('Unknown password KDF: %s', $identifier));
        }

        return $this->kdfs[$identifier];
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->kdfs);
    }
}
