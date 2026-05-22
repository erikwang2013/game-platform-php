<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\AsymmetricCipherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 非对称加解密实现注册表。
 */
final class AsymmetricCipherRegistry
{
    /** @var array<string, AsymmetricCipherInterface> */
    private array $ciphers = [];

    public function __construct(AsymmetricCipherInterface ...$ciphers)
    {
        foreach ($ciphers as $c) {
            $this->register($c);
        }
    }

    public function register(AsymmetricCipherInterface $cipher): self
    {
        $id = $cipher->getIdentifier();
        if ($id === '') {
            throw new EncryptionException('Asymmetric cipher identifier must not be empty.');
        }
        $this->ciphers[$id] = $cipher;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->ciphers[$identifier]);
    }

    public function get(string $identifier): AsymmetricCipherInterface
    {
        if (!isset($this->ciphers[$identifier])) {
            throw new EncryptionException(sprintf('Unknown asymmetric cipher: %s', $identifier));
        }

        return $this->ciphers[$identifier];
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->ciphers);
    }
}
