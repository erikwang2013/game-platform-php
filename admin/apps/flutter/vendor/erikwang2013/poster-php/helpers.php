<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;

if (!function_exists('captcha_create')) {
    function captcha_create(string $type = 'click', array $options = []): array
    {
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        $captcha = $manager->create($type);
        if (isset($options['difficulty'])) $captcha->setDifficulty($options['difficulty']);
        if (isset($options['background'])) $captcha->setBackground($options['background']);
        return $captcha->generate();
    }
}

if (!function_exists('captcha_verify')) {
    function captcha_verify(string $key, string $type, mixed $data): bool
    {
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        return $manager->verify($key, ['type' => $type, 'data' => $data]);
    }
}

if (!function_exists('poster_create')) {
    function poster_create(?int $width = null, ?int $height = null): PosterBuilder
    {
        $builder = new PosterBuilder(DriverFactory::create(PosterConfig::get('image.driver')));
        if ($width !== null) $builder->width($width);
        if ($height !== null) $builder->height($height);
        return $builder;
    }
}
