<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Coupon extends Model
{
    protected $table = 'erik_coupon';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'type',
        'amount',
        'min_amount',
        'max_per_user',
        'total_qty',
        'used_qty',
        'start_at',
        'end_at',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'string',
        'min_amount' => 'string',
        'max_per_user' => 'int',
        'total_qty' => 'int',
        'used_qty' => 'int',
        'status' => 'int',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
