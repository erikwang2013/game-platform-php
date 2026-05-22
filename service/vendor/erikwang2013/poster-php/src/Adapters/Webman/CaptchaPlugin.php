<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman;

use Webman\Bootstrap;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaPlugin implements Bootstrap
{
    public static function start($worker): void { PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php'); }
    public static function captcha(): CaptchaManager
    {
        return new CaptchaManager(DriverFactory::create(PosterConfig::get('image.driver')), StorageFactory::create(PosterConfig::get('captcha.storage')));
    }
}
