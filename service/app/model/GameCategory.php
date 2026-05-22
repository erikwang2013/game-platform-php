<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class GameCategory extends Model
{
    protected $table = 'erik_game_category';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort',
        'status',
    ];

    protected $casts = [
        'sort' => 'int',
        'status' => 'int',
    ];
}
