<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * Hyperf：置于 {@code config/autoload/plugins/{vendor}/{package}.php}，
 * 合并键为 {@code plugins.{vendor}.{package}.*}。
 *
 * @see https://hyperf.wiki/en/config.html
 */
return [
    'key' => env('ENCRYPTION_KEY'),
    'cipher' => env('ENCRYPTION_CIPHER', 'aes-256-gcm'),
    'previous_keys' => \Erikwang2013\Encryptable\Support\PreviousKeysParser::parse(env('ENCRYPTION_PREVIOUS_KEYS')),
];
