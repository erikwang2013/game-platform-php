<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\HasherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 杂凑算法注册表。
 */
final class HasherRegistry
{
    /** @var array<string, HasherInterface> */
    private array $hashers = [];

    public function __construct(HasherInterface ...$hashers)
    {
        foreach ($hashers as $h) {
            $this->register($h);
        }
    }

    public function register(HasherInterface $hasher): self
    {
        $id = $hasher->getIdentifier();
        if ($id === '') {
            throw new EncryptionException('Hasher identifier must not be empty.');
        }
        $this->hashers[$id] = $hasher;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->hashers[$identifier]);
    }

    public function get(string $identifier): HasherInterface
    {
        if (!isset($this->hashers[$identifier])) {
            throw new EncryptionException(sprintf('Unknown hasher: %s', $identifier));
        }

        return $this->hashers[$identifier];
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->hashers);
    }
}
