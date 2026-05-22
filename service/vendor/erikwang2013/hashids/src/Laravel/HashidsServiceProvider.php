<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\Laravel;

use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids as HashidsClient;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

final class HashidsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $source = dirname(__DIR__, 2) . '/config/hashids.php';

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $source => config_path('hashids.php'),
            ], 'hashids-config');
        }
    }

    public function register(): void
    {
        $source = dirname(__DIR__, 2) . '/config/hashids.php';
        $this->mergeConfigFrom($source, 'hashids');

        $this->app->singleton(HashidsFactory::class, static fn (): HashidsFactory => new HashidsFactory());

        $this->app->singleton(HashidsManager::class, function ($app): HashidsManager {
            /** @var array $cfg */
            $cfg = $app['config']->get('hashids', []);

            return new HashidsManager(is_array($cfg) ? $cfg : [], $app->make(HashidsFactory::class));
        });

        $this->app->alias(HashidsManager::class, 'hashids');

        $this->app->bind(HashidsClient::class, static fn ($app): HashidsClient => $app->make(HashidsManager::class)->connection());
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            HashidsFactory::class,
            HashidsManager::class,
            'hashids',
            HashidsClient::class,
        ];
    }
}
