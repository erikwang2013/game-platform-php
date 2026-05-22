<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class UserOauth extends Model
{
    protected $table = 'erik_user_oauth';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'provider',
        'open_id',
        'union_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'raw_data',
    ];

    protected $casts = [
        'access_token' => Encryptable::class,
        'refresh_token' => Encryptable::class,
        'token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
