<?php

declare(strict_types=1);

namespace CryptoSm\SM2;

/**
 * Options for SM2 encryption/decryption operations.
 */
class Sm2CipherOptions
{
    private int $cipherMode = 1;

    public function getCipherMode(): int
    {
        return $this->cipherMode;
    }

    public function setCipherMode(int $cipherMode): self
    {
        $this->cipherMode = $cipherMode;
        return $this;
    }
}
