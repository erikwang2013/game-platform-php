<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Illuminate\Support\ServiceProvider;

class PosterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/poster.php', 'poster');
        $this->app->singleton('poster.builder', fn() => new PosterBuilder(DriverFactory::create(config('poster.image.driver'))));
        $this->app->alias('poster.builder', PosterBuilder::class);
    }
    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 3) . '/config/poster.php' => config_path('poster.php')], 'poster-config');
    }
}
