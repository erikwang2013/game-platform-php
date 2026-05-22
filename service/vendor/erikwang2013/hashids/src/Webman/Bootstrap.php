<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\Webman;

use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids as HashidsClient;
use support\Container;
use Webman\Bootstrap as WebmanBootstrapContract;
use Workerman\Worker;

use function config;

final class Bootstrap implements WebmanBootstrapContract
{
    public static function start(?Worker $worker): void
    {
        $plugin = config('plugin.erikwang2013.hashids');
        if (!($plugin['enable'] ?? true)) {
            return;
        }

        $hashidsConfig = config('hashids');
        if (!is_array($hashidsConfig)) {
            $hashidsConfig = [];
        }

        $factory = new HashidsFactory();
        $manager = new HashidsManager($hashidsConfig, $factory);

        Container::instance()->addDefinitions([
            HashidsFactory::class => static fn (): HashidsFactory => $factory,
            HashidsManager::class => static fn (): HashidsManager => $manager,
            'hashids' => static fn (): HashidsManager => $manager,
            HashidsClient::class => static fn (): HashidsClient => $manager->connection(),
        ]);
    }
}
