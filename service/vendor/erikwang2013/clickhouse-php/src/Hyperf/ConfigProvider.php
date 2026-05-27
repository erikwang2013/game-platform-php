<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                ClickHouseConnection::class => function ($container) {
                    $config = $container->get(\Hyperf\Contract\ConfigInterface::class);
                    return new ClickHouseConnection(
                        new Pool\PoolFactory($config->get('clickhouse', [])),
                    );
                },
            ],
            'commands' => [
                Command\ClickHouseCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'clickhouse-config',
                    'description' => 'ClickHouse configuration',
                    'source' => __DIR__ . '/config/clickhouse.php',
                    'destination' => BASE_PATH . '/config/autoload/clickhouse.php',
                ],
            ],
        ];
    }
}