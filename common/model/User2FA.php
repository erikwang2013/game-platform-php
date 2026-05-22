<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

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

    protected $casts = [
        'secret' => Encryptable::class,
        'backup_codes' => Encryptable::class,
        'is_enabled' => 'int',
        'enabled_at' => 'datetime',
    ];
}
