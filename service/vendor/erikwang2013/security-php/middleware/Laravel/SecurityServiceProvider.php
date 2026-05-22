<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Laravel;

use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/security.php',
            'security'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2) . '/config/security.php' => config_path('security.php'),
        ], 'security-config');

        \Erikwang2013\Security\SecurityGuard::init(config('security'));

        $this->app['router']->aliasMiddleware('security', SecurityMiddleware::class);
    }
}
