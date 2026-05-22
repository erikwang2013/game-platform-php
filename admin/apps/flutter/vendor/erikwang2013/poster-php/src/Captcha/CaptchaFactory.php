<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;

class CaptchaFactory
{
    public static function create(
        string $type,
        ImageDriverInterface $imageDriver,
        StorageInterface $storage
    ): CaptchaInterface {
        return match ($type) {
            'click'   => new ClickCaptcha($imageDriver, $storage),
            'rotate'  => new RotateCaptcha($imageDriver, $storage),
            'slider'  => new SliderCaptcha($imageDriver, $storage),
            default   => throw new InvalidArgumentException("Unknown captcha type: $type"),
        };
    }
}
