<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Leaderboard extends Model
{
    protected $table = 'erik_leaderboard';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'game_id',
        'name',
        'type',
        'metric',
        'rule',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
    ];
}
