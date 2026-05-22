<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class AdminRole extends Model
{
    protected $table = 'erik_admin_role';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'slug', 'description', 'status'];
    protected $casts = ['status' => 'integer'];

    public function permissions()
    {
        return $this->belongsToMany(AdminPermission::class, 'erik_admin_role_permission', 'role_id', 'permission_id');
    }

    public function users()
    {
        return $this->belongsToMany(AdminUser::class, 'erik_admin_user_role', 'role_id', 'user_id');
    }
}
