<?php

declare(strict_types=1);

namespace Erikwang2013\Season\Hyperf;

use Erikwang2013\Season\SeasonService;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * Hyperf 2 / 3
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                SeasonService::class => function (ContainerInterface $container): SeasonService {
                    $config = $container->get(ConfigInterface::class);
                    $code = $config->get('country_season.default_country_code', 'CN');

                    return new SeasonService(\is_string($code) ? $code : 'CN');
                },
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'country season (default country code for SeasonService)',
                    'source' => dirname(__DIR__, 2) . '/config/country_season.php',
                    'destination' => \defined('BASE_PATH') ? BASE_PATH . '/config/autoload/country_season.php' : '',
                ],
            ],
        ];
    }
}
