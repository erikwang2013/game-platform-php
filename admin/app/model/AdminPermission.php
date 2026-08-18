<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class AdminPermission extends Model
{
    protected $table = 'admin_permission';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['parent_id', 'name', 'slug', 'type', 'icon', 'path', 'sort'];
    protected $casts = [
        'parent_id' => 'integer',
        'type' => 'integer',
        'sort' => 'integer',
    ];

    public function children()
    {
        return $this->hasMany(AdminPermission::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(AdminPermission::class, 'parent_id');
    }

    public function roles()
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_permission', 'permission_id', 'role_id');
    }
}
