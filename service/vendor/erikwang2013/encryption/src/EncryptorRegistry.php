<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 注册多种加密实现，按标识解析；可运行时注册自定义插件。
 */
final class EncryptorRegistry
{
    /** @var array<string, EncryptorInterface> */
    private array $encryptors = [];

    public function __construct(EncryptorInterface ...$encryptors)
    {
        foreach ($encryptors as $e) {
            $this->register($e);
        }
    }

    public function register(EncryptorInterface $encryptor): self
    {
        $id = $encryptor->getIdentifier();
        if ($id === '') {
            throw new EncryptionException('Encryptor identifier must not be empty.');
        }
        $this->encryptors[$id] = $encryptor;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->encryptors[$identifier]);
    }

    public function get(string $identifier): EncryptorInterface
    {
        if (!isset($this->encryptors[$identifier])) {
            throw new EncryptionException(sprintf('Unknown encryptor: %s', $identifier));
        }

        return $this->encryptors[$identifier];
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->encryptors);
    }
}
