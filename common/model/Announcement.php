<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Announcement extends Model
{
    protected $table = 'erik_announcement';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'title',
        'content',
        'type',
        'target_lang',
        'status',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'status' => 'int',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
