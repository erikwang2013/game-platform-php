<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Laravel;

use Erikwang2013\Jwt\JWTFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class JWTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/jwt.php', 'jwt');

        $this->app->singleton('erik.jwt', function ($app) {
            $config = $app['config']->get('jwt', []);
            return JWTFactory::createFromConfig($config, $app['log']->channel(), [
                'redis' => fn() => Redis::connection()->client(),
                'pdo'   => DB::connection()->getPdo(),
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/jwt.php' => config_path('jwt.php'),
            ], 'jwt-config');

            $this->commands([InstallCommand::class]);
        }

        $this->app['router']->aliasMiddleware('jwt', Middleware::class);
    }
}
