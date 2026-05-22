<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Utils;

use Erikwang2013\Encryptable\Exceptions\SerializationException;
use Erikwang2013\Encryptable\Exceptions\UnserializationException;

class Serializer
{
    const SUPPORTED_TYPES = [
        'string',
        'integer',
        'double',
        'boolean',
        'NULL',
    ];

    public static function serialize(mixed $value): string
    {
        $valueType = gettype($value);

        if (! in_array($valueType, self::SUPPORTED_TYPES, true)) {
            throw new SerializationException;
        }

        if ($valueType === 'NULL') {
            return 'NULL:';
        }

        $value = strval($value);

        return "{$valueType}:{$value}";
    }

    public static function unserialize(string $payload): mixed
    {
        $payload = explode(':', $payload, 2);

        if (count($payload) !== 2) {
            throw new UnserializationException;
        }

        [$valueType, $value] = $payload;

        if ($valueType === 'NULL') {
            return null;
        }

        if (! in_array($valueType, self::SUPPORTED_TYPES, true)) {
            throw new UnserializationException;
        }

        if (! settype($value, $valueType)) {
            throw new UnserializationException;
        }

        return $value;
    }
}
