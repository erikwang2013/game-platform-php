<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class DepositOrder extends Model
{
    protected $table = 'erik_deposit_order';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_no',
        'user_id',
        'amount',
        'currency',
        'platform_amount',
        'payment_method_id',
        'status',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'string',
        'platform_amount' => 'string',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
