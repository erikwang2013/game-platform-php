<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [PosterBuilder::class => PosterBuilderFactory::class],
            'publish' => [[
                'id' => 'poster-poster-config', 'description' => 'Poster-php poster config',
                'source' => dirname(__DIR__, 3) . '/config/poster.php', 'destination' => BASE_PATH . '/config/autoload/poster.php',
            ]],
        ];
    }
}

class PosterBuilderFactory
{
    public function __invoke(): PosterBuilder
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver')));
    }
}
