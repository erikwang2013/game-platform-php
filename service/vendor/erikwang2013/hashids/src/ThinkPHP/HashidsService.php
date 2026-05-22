<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\ThinkPHP;

use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids as HashidsClient;
use think\Service as ThinkService;

final class HashidsService extends ThinkService
{
    public function register(): void
    {
        $this->app->bind(HashidsFactory::class, static fn (): HashidsFactory => new HashidsFactory());

        $this->app->bind(HashidsManager::class, function (): HashidsManager {
            $cfg = $this->app->config->get('hashids');

            return new HashidsManager(is_array($cfg) ? $cfg : [], $this->app->make(HashidsFactory::class));
        });

        $this->app->bind('hashids', fn (): HashidsManager => $this->app->make(HashidsManager::class));

        $this->app->bind(HashidsClient::class, fn (): HashidsClient => $this->app->make(HashidsManager::class)->connection());
    }
}
