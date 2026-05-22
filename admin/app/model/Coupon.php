<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Coupon extends Model
{
    protected $table = 'erik_coupon';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'game_id',
        'total_qty',
        'used_qty',
        'user_limit',
        'start_at',
        'end_at',
        'status',
    ];

    protected $casts = [
        'value' => 'string',
        'min_amount' => 'string',
        'max_discount' => 'string',
        'total_qty' => 'int',
        'used_qty' => 'int',
        'user_limit' => 'int',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'status' => 'int',
    ];
}
