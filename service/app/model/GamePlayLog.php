<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class GamePlayLog extends Model
{
    protected $table = 'erik_game_play_log';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game_id',
        'server_id',
        'session_id',
        'action',
        'game_amount_before',
        'game_amount_change',
        'game_amount_after',
        'platform_amount_change',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'game_amount_before' => 'string',
        'game_amount_change' => 'string',
        'game_amount_after' => 'string',
        'platform_amount_change' => 'string',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
