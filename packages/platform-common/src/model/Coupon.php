<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

/**
 * 优惠券定义。
 *
 * 领取已实现（CouponController::claim()）；**核销/抵扣未实现** —— used_qty 仅随领取递增，
 * 全仓无写入方把券标记为已使用。
 */
class Coupon extends Model
{
    protected $table = 'coupon';

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
        'conditions',
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
