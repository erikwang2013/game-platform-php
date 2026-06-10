<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class GameServer extends Model
{
    protected $table = 'game_server';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'game_id',
        'name',
        'region',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
