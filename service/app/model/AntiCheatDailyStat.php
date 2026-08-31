<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 反作弊每日统计 — uk(user_id, game_id, stat_date)
 */
class AntiCheatDailyStat extends Model
{
    protected $table = 'anticheat_daily_stat';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'user_id',
        'game_id',
        'stat_date',
        'rounds',
        'wins',
        'bets',
        'avg_bet',
        'std_bet',
        'wins_total',
        'plays_30d',
        'wins_30d',
        'active_seconds',
        'moves_per_sec_p50',
    ];

    protected $casts = [
        'user_id' => 'int',
        'game_id' => 'int',
        'stat_date' => 'date',
        'rounds' => 'int',
        'wins' => 'int',
        'bets' => 'string',
        'avg_bet' => 'string',
        'std_bet' => 'string',
        'wins_total' => 'int',
        'plays_30d' => 'int',
        'wins_30d' => 'int',
        'active_seconds' => 'int',
        'moves_per_sec_p50' => 'float',
    ];
}
