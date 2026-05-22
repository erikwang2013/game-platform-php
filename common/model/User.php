<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\Encryptable\Encryptable;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'erik_user';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'username',
        'password',
        'nickname',
        'avatar',
        'email',
        'phone',
        'country',
        'language',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => 'int',
        'email' => Encryptable::class,
        'phone' => Encryptable::class,
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->hasOne(UserWallet::class, 'user_id');
    }

    public function oauthAccounts()
    {
        return $this->hasMany(UserOauth::class, 'user_id');
    }
}
