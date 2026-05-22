<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman\Facades;

use Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin;

class Captcha
{
    public static function __callStatic(string $method, array $args) { return CaptchaPlugin::captcha()->$method(...$args); }
    public static function create(string $type) { return CaptchaPlugin::captcha()->create($type); }
    public static function verify(string $key, array $data): bool { return CaptchaPlugin::captcha()->verify($key, $data); }
}
