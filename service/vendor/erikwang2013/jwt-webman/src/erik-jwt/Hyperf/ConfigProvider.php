<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Erikwang2013\Jwt\JWT::class => function (ContainerInterface $container) {
                    $config = $container->get(ConfigInterface::class)->get('jwt', []);
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);

                    $connections = [];
                    if (($config['storage']['type'] ?? '') === 'redis') {
                        $connections['redis'] = fn() => $container->get(\Hyperf\Redis\Redis::class);
                    }
                    if (($config['storage']['type'] ?? '') === 'database') {
                        $connections['pdo'] = $container->get(\Hyperf\DbConnection\Db::class)->connection()->getPdo();
                    }
                    if (($config['storage']['type'] ?? '') === 'memcached' && $container->has(\Memcached::class)) {
                        $connections['memcached'] = $container->get(\Memcached::class);
                    }

                    return \Erikwang2013\Jwt\JWTFactory::createFromConfig($config, $logger, $connections);
                },
            ],
            'commands' => [
                InstallCommand::class,
            ],
            'publish' => [
                [
                    'id'          => 'config',
                    'description' => 'JWT config file.',
                    'source'      => __DIR__ . '/config/jwt.php',
                    'destination' => BASE_PATH . '/config/autoload/jwt.php',
                ],
            ],
        ];
    }
}
