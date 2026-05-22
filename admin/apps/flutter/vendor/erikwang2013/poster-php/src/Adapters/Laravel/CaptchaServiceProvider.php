<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Illuminate\Support\ServiceProvider;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/poster.php', 'poster');
        $this->app->singleton('poster.captcha', fn() => new CaptchaManager(
            DriverFactory::create(config('poster.image.driver')),
            StorageFactory::create(config('poster.captcha.storage'))
        ));
        $this->app->alias('poster.captcha', CaptchaManager::class);
    }
    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 3) . '/config/poster.php' => config_path('poster.php')], 'poster-config');
    }
}
