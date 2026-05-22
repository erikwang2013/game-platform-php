<?php

declare(strict_types=1);

namespace CryptoSm\SM4;

/**
 * Options for SM4 encryption/decryption operations.
 */
class Sm4Options
{
    private string $padding = 'pkcs5';
    private string $mode = 'ecb';
    private string $iv = '';

    public function getPadding(): string
    {
        return $this->padding;
    }

    public function setPadding(string $padding): self
    {
        $this->padding = $padding;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function getIv(): string
    {
        return $this->iv;
    }

    public function setIv(string $iv): self
    {
        $this->iv = $iv;
        return $this;
    }
}
