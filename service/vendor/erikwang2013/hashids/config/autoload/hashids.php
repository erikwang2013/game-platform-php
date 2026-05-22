<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

/**
 * Hyperf: publish to config/autoload/hashids.php — ConfigInterface key "hashids".
 */

return [
    'hashids' => [
        'default' => 'main',

        'connections' => [
            'main' => [
                'salt' => '',
                'length' => 0,
            ],

            'alternative' => [
                'salt' => 'your-salt-string',
                'length' => 0,
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
    ],
];
