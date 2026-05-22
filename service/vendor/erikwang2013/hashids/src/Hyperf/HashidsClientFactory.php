<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\Hyperf;

use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids as HashidsClient;
use Psr\Container\ContainerInterface;

final class HashidsClientFactory
{
    public function __invoke(ContainerInterface $container): HashidsClient
    {
        return $container->get(HashidsManager::class)->connection();
    }
}
