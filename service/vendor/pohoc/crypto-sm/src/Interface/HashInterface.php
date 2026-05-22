<?php

declare(strict_types=1);

namespace CryptoSm\Interface;

interface HashInterface
{
    public function hash(string $data): string;
}
