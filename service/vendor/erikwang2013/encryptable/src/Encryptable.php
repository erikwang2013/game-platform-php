<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Encryptable implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): mixed
    {
        return Encryption::php()->decrypt($value);
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        return Encryption::php()->encrypt($value);
    }
}
