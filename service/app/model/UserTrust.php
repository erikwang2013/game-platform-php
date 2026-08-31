<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 用户信任分 — uk(user_id)
 */
class UserTrust extends Model
{
    protected $table = 'user_trust';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'user_id',
        'score',
        'band',
        'hit_count',
        'last_hit_at',
        'whitelisted',
        'whitelist_by',
        'whitelist_note',
    ];

    protected $casts = [
        'user_id' => 'int',
        'score' => 'int',
        'hit_count' => 'int',
        'last_hit_at' => 'datetime',
        'whitelisted' => 'int',
        'whitelist_by' => 'int',
    ];
}
