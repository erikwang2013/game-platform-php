<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class GroupMember extends Model
{
    protected $table = 'group_member';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'contrib',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'contrib' => 'int',
    ];
}
