<?php

declare(strict_types=1);

namespace CryptoSm\SM2;

/**
 * Options for SM2 signature operations.
 */
class SignatureOptions
{
    private bool $der = false;
    private bool $hash = false;
    private string $publicKey = '';
    private string $userId = '1234567812345678';

    public function getDer(): bool
    {
        return $this->der;
    }

    public function setDer(bool $der): self
    {
        $this->der = $der;
        return $this;
    }

    public function getHash(): bool
    {
        return $this->hash;
    }

    public function setHash(bool $hash): self
    {
        $this->hash = $hash;
        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
}
