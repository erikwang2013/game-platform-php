<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [CaptchaManager::class => CaptchaManagerFactory::class],
            'publish' => [[
                'id' => 'poster-config', 'description' => 'Poster-php captcha config',
                'source' => dirname(__DIR__, 3) . '/config/poster.php', 'destination' => BASE_PATH . '/config/autoload/poster.php',
            ]],
        ];
    }
}

class CaptchaManagerFactory
{
    public function __invoke(): CaptchaManager
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new CaptchaManager(DriverFactory::create(PosterConfig::get('image.driver')), StorageFactory::create(PosterConfig::get('captcha.storage')));
    }
}
