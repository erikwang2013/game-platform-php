<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\ThinkPHP;

use Erikwang2013\Jwt\JWTFactory;
use think\Service;

class JWTService extends Service
{
    public function register(): void
    {
        $this->app->bind('erik.jwt', function ($app) {
            $config = $app->config->get('jwt', []);

            $connections = [];
            if (($config['storage']['type'] ?? '') === 'redis') {
                $connections['redis'] = fn() => \think\facade\Cache::store('redis')->handler();
            }
            if (($config['storage']['type'] ?? '') === 'database') {
                $connections['pdo'] = \think\facade\Db::connect()->getPdo();
            }
            if (($config['storage']['type'] ?? '') === 'memcached') {
                $connections['memcached'] = \think\facade\Cache::store('memcached')->handler();
            }

            return JWTFactory::createFromConfig($config, null, $connections);
        });
    }

    public function boot(): void
    {
        $this->app->middleware->alias('jwt', Middleware::class);
    }
}
