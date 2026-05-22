<?php

declare(strict_types=1);

namespace Erikwang2013\Season\Laravel;

use Erikwang2013\Season\SeasonService;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel 7 / 8 / 9 / 10 / 11
 */
class CountrySeasonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/country_season.php',
            'country_season'
        );

        $this->app->singleton(SeasonService::class, function ($app): SeasonService {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app['config'];
            $code = $config->get('country_season.default_country_code', 'CN');

            return new SeasonService(\is_string($code) ? $code : 'CN');
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__, 2) . '/config/country_season.php' => $this->app->configPath('country_season.php'),
            ], 'country-season-config');
        }
    }
}
