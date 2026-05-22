<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReferralReward extends Model
{
    protected $table = 'erik_referral_reward';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'referral_id',
        'user_id',
        'type',
        'amount',
        'source_amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'string',
        'source_amount' => 'string',
    ];

    /**
     * Boot the model — set created_at on create.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? date('Y-m-d H:i:s');
        });
    }
}
