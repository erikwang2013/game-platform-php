<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

// 勿加 Searchable：全仓无任何地方搜索管理员，而该 trait 在 saved 钩子里解析搜索引擎，
// 会让登录写 last_login_at 时硬依赖 OpenSearch（未安装 opensearch-php 则注册/登录 500）
class AdminUser extends Model
{
    use SoftDeletes;

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
}
