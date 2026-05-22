<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Support;

/**
 * Normalizes {@code previous_keys} / {@code ENCRYPTION_PREVIOUS_KEYS} from env or config.
 */
final class PreviousKeysParser
{
    /**
     * @return list<non-empty-string>
     */
    public static function parse(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (is_array($raw)) {
            return self::normalizeList($raw);
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }

        if (str_starts_with($s, '[')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return self::normalizeList($decoded);
            }
        }

        return self::normalizeList(array_map('trim', explode(',', $s)));
    }

    /**
     * @param array<int|string, mixed> $items
     * @return list<non-empty-string>
     */
    private static function normalizeList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_string($item) && ! is_int($item) && ! is_float($item)) {
                continue;
            }
            $k = trim((string) $item);
            if ($k === '') {
                continue;
            }
            $out[] = $k;
        }

        return $out;
    }
}
