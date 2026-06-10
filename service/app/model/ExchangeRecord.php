<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ExchangeRecord extends Model
{
    protected $table = 'exchange_record';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game_id',
        'currency_id',
        'direction',
        'platform_amount',
        'game_amount',
        'rate',
        'spread_fee',
    ];

    protected $casts = [
        'platform_amount' => 'string',
        'game_amount' => 'string',
        'rate' => 'string',
        'spread_fee' => 'string',
    ];
}
