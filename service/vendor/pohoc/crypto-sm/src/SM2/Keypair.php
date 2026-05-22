<?php

declare(strict_types=1);

namespace CryptoSm\SM2;

/**
 * Represents an SM2 key pair.
 */
class Keypair
{
    private string $privateKey;
    private string $publicKey;

    public function __construct(string $privateKey, string $publicKey)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
