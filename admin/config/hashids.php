<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

// fail-closed：salt 缺失或仍为默认值时拒绝启动，否则 hashid 可逆、IDOR 防护失效
$hashidsMainSalt = getenv('HASHIDS_SALT');
if (!$hashidsMainSalt || $hashidsMainSalt === 'game-platform-hashids-salt-2026') {
    throw new \RuntimeException('HASHIDS_SALT 环境变量缺失或仍为默认值，拒绝启动');
}
$hashidsAltSalt = getenv('HASHIDS_ALT_SALT');
if (!$hashidsAltSalt || $hashidsAltSalt === 'game-platform-alt-salt-2026') {
    throw new \RuntimeException('HASHIDS_ALT_SALT 环境变量缺失或仍为默认值，拒绝启动');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | The name of the default Hashids connection.
    |
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Hashids Connections
    |--------------------------------------------------------------------------
    |
    | Configure named connections. Options mirror vinkla/hashids:
    | - salt: secret salt string
    | - length: minimum hash length (integer)
    | - alphabet: optional custom alphabet
    |
    */

    'connections' => [

        'main' => [
            'salt' => $hashidsMainSalt,
            'length' => 0,
            // 'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        ],

        'alternative' => [
            'salt' => $hashidsAltSalt,
            'length' => 0,
            // 'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Security Warning
    |--------------------------------------------------------------------------
    |
    | Always set a unique, random salt per connection before deploying.
    | An empty or guessable salt makes your hashids trivially reversible.
    | Use env('HASHIDS_SALT') or an equally strong source per environment.
    |
    */

];
