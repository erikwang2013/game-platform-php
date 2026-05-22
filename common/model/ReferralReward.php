<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class ReferralReward extends Model
{
    protected $table = 'erik_referral_reward';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'referral_id',
        'user_id',
        'type',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'string',
        'status' => 'int',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
