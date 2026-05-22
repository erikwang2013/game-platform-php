<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class WithdrawOrder extends Model
{
    protected $table = 'erik_withdraw_order';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_no',
        'user_id',
        'platform_amount',
        'fiat_amount',
        'currency',
        'method',
        'account_info',
        'status',
        'reviewer_id',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'platform_amount' => 'string',
        'fiat_amount' => 'string',
        'account_info' => Encryptable::class,
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
