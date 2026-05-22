<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class User2FA extends Model
{
    protected $table = 'erik_user_2fa';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'secret',
        'is_enabled',
        'backup_codes',
        'enabled_at',
    ];

    protected $hidden = [
        'secret',
        'backup_codes',
    ];

    protected $casts = [
        'is_enabled' => 'int',
        'backup_codes' => 'json',
        'enabled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
