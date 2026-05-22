<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman\Facades;

use Erikwang2013\Poster\Adapters\Webman\PosterPlugin;

class Poster
{
    public static function __callStatic(string $method, array $args) { return PosterPlugin::builder()->$method(...$args); }
}
