<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Exceptions\MissingEncryptionCipherException;
use Erikwang2013\Encryptable\Exceptions\MissingEncryptionKeyException;

abstract class Encrypter
{
    const DIRTY_BIT_KEY = 'crypt:';

    public function __construct(
        protected EncryptableConfigContract $encryptableConfig
    ) {
    }

    abstract public function encrypt(mixed $value, bool $serialize = true): ?string;

    abstract public function decrypt(?string $payload, bool $unserialize = true): mixed;

    protected function getEncryptionKey(): string
    {
        $key = $this->encryptableConfig->getKey();

        if ($key === null || $key === '') {
            throw new MissingEncryptionKeyException;
        }

        return $key;
    }

    protected function getEncryptionCipher(): string
    {
        $cipher = $this->encryptableConfig->getCipher();

        if ($cipher === null || $cipher === '') {
            throw new MissingEncryptionCipherException;
        }

        return $cipher;
    }

    /**
     * Primary key first, then {@see EncryptableConfigContract::getPreviousKeys()} (deduplicated).
     *
     * @return list<string>
     */
    protected function getDecryptionKeyRing(): array
    {
        $primary = $this->getEncryptionKey();
        $ring = [$primary];

        foreach ($this->encryptableConfig->getPreviousKeys() as $key) {
            $key = trim((string) $key);
            if ($key === '' || $key === $primary) {
                continue;
            }
            if (in_array($key, $ring, true)) {
                continue;
            }
            $ring[] = $key;
        }

        return $ring;
    }
}
