<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Group extends Model
{
    protected $table = 'group';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'type',
        'name',
        'game_id',
        'owner_id',
        'level',
        'xp',
        'member_count',
        'announcement',
        'expire_at',
        'status',
    ];

    protected $casts = [
        'status' => 'int',
        'level' => 'int',
        'member_count' => 'int',
    ];

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }
}
