<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class UserSession extends Model
{
    protected $table = 'user_session';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'token_id',
        'device',
        'ip',
        'location',
        'user_agent',
        'logged_in_at',
        'expired_at',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'expired_at' => 'datetime',
    ];
}
