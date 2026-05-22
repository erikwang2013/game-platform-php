<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\Hyperf;

use Erikwang2013\Encryptable\Contracts\DbDriverDetector;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\DBEncrypter;
use Erikwang2013\Encryptable\PHPEncrypter;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                EncryptableConfigContract::class => HyperfEncryptableConfig::class,
                DbDriverDetector::class => HyperfDbDriverDetector::class,
                PHPEncrypter::class => PHPEncrypter::class,
                DBEncrypter::class => DBEncrypter::class,
            ],
        ];
    }
}
