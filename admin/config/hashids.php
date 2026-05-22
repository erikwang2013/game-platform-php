<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

declare(strict_types=1);

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
            // 盐值，生产环境请使用环境变量 HASHIDS_SALT 注入随机字符串
            'salt' => getenv('HASHIDS_SALT') ?: 'open-admin-hashids-salt-2026',
            // 生成的 hash 最小长度，16 位可有效避免碰撞
            'length' => 16,
            // 自定义字符集，62 个字符的混合字母数字
            'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        ],

        'alternative' => [
            'salt' => getenv('HASHIDS_ALT_SALT') ?: 'open-admin-alt-salt-2026',
            'length' => 16,
            'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Security Warning
    |--------------------------------------------------------------------------
    |
    | Always set a unique, random salt per connection before deploying.
    | An empty or guessable salt makes your hashids trivially reversible.
    | Use getenv('HASHIDS_SALT') or an equally strong source per environment.
    |
    */

];
