<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\KeyDerivationInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 密钥派生（HKDF 等）实现注册表。
 */
final class KeyDerivationRegistry
{
    /** @var array<string, KeyDerivationInterface> */
    private array $kdfs = [];

    public function __construct(KeyDerivationInterface ...$kdfs)
    {
        foreach ($kdfs as $k) {
            $this->register($k);
        }
    }

    public function register(KeyDerivationInterface $kdf): self
    {
        $id = $kdf->getIdentifier();
        if ($id === '') {
            throw new EncryptionException('KDF identifier must not be empty.');
        }
        $this->kdfs[$id] = $kdf;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->kdfs[$identifier]);
    }

    public function get(string $identifier): KeyDerivationInterface
    {
        if (!isset($this->kdfs[$identifier])) {
            throw new EncryptionException(sprintf('Unknown KDF: %s', $identifier));
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
