<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf\Facades;

use Hyperf\Context\ApplicationContext;
use Erikwang2013\Poster\Captcha\CaptchaManager;

class Captcha
{
    public static function __callStatic(string $method, array $args) { return ApplicationContext::getContainer()->get(CaptchaManager::class)->$method(...$args); }
    public static function create(string $type) { return ApplicationContext::getContainer()->get(CaptchaManager::class)->create($type); }
    public static function verify(string $key, array $data): bool { return ApplicationContext::getContainer()->get(CaptchaManager::class)->verify($key, $data); }
}
