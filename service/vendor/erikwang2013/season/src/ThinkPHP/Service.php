<?php

declare(strict_types=1);

namespace Erikwang2013\Season\ThinkPHP;

use Erikwang2013\Season\SeasonService;
use think\Service as ThinkService;

/**
 * ThinkPHP 6 / 8（需已安装 topthink/framework）
 */
class Service extends ThinkService
{
    public function register(): void
    {
        $path = dirname(__DIR__, 2) . '/config/country_season.php';
        if (\is_file($path)) {
            $cfg = include $path;
            if (\is_array($cfg)) {
                $this->app->config->set(['country_season' => $cfg]);
            }
        }

        $this->app->singleton(SeasonService::class, function () {
            $config = $this->app->config->get('country_season', []);
            $code = \is_array($config) ? ($config['default_country_code'] ?? 'CN') : 'CN';

            return new SeasonService(\is_string($code) ? $code : 'CN');
        });
    }
}
