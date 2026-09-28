<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

/**
 * 用户已领优惠券。
 *
 * 领取已实现（CouponController::claim()）；**核销/抵扣未实现** —— status='used' 与 used_in_order
 * 全仓无写入方（唯一命中是 $fillable 与 CouponController::my() 的读过滤器），券领到手后永不消耗。
 */
class UserCoupon extends Model
{
    protected $table = 'user_coupon';

    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false; // 该表无 updated_at（见 install.sql），Eloquent 写它必 Unknown column；created_at 由 DDL 默认值兜

    protected $fillable = [
        'user_id',
        'coupon_id',
        'status',
        'used_at',
        'used_in_order',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
