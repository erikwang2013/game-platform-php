<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class GamePlayLog extends Model
{
    protected $table = 'game_play_log';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game_id',
        'server_id',
        'session_id',
        'round_id',
        'action',
        'game_amount_before',
        'game_amount_change',
        'game_amount_after',
        'bet_amount',
        'win_amount',
        'platform_amount_change',
        'ip_hash',
        'user_agent_hash',
        'device_id',
        'ended_at_round',
        'level_id',
        'move_count',
        'result',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'game_amount_before' => 'string',
        'game_amount_change' => 'string',
        'game_amount_after' => 'string',
        'bet_amount' => 'string',
        'win_amount' => 'string',
        'platform_amount_change' => 'string',
        'ended_at_round' => 'datetime',
        'level_id' => 'int',
        'move_count' => 'int',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
