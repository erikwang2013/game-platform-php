<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class AdminUser extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $table = 'admin_user';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'username', 'password', 'real_name', 'avatar',
        'email', 'phone', 'id_card', 'status',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'id_card'];
    protected $casts = [
        'status' => 'integer',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email' => Encryptable::class,
        'phone' => Encryptable::class,
        'id_card' => Encryptable::class,
    ];

    public function roles()
    {
        return $this->belongsToMany(AdminRole::class, 'admin_user_role', 'user_id', 'role_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'username'  => $this->username,
            'real_name' => $this->real_name,
        ];
    }
}
