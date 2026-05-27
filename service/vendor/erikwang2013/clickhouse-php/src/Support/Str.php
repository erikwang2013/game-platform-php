<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Support;

class Str
{
    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($value)));
    }
}