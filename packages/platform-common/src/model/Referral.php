<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Referral extends Model
{
    protected $table = 'referral';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'code',
        'status',
        'parent_id',
    ];

    protected $casts = [
        'status' => 'int',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}
