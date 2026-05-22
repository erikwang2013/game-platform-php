<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Exceptions;

use RuntimeException;

class UnserializationException extends RuntimeException
{
    public function __construct(string $message = 'The given value cannot be unserialized.')
    {
        parent::__construct($message);
    }
}
