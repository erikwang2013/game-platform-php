<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Support;

class Arr
{
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function only(array $array, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $result[$key] = $array[$key];
            }
        }
        return $result;
    }
}