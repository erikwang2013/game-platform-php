<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman;

use Webman\Bootstrap;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterPlugin implements Bootstrap
{
    public static function start($worker): void { PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php'); }
    public static function builder(): PosterBuilder { return new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver'))); }
}
