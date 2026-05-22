<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Laravel;

use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static string encode(array $payload, int $expire = 0, array $headers = [])
 * @method static array  decode(string $token)
 * @method static bool   validate(string $token)
 * @method static string refresh(string $token, int $newExpire = 3600)
 * @method static bool   blacklist(string $token)
 * @method static bool   isBlacklisted(string $token)
 */
class Facade extends LaravelFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'erik.jwt';
    }
}
