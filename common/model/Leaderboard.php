<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Leaderboard extends Model
{
    protected $table = 'erik_leaderboard';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'type',
        'description',
        'status',
        'sort',
        'config',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
        'config' => 'json',
    ];
}
