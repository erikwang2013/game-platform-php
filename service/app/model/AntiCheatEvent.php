<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 反作弊命中事件 — uk(user_id, rule_type, stat_date) 幂等
 */
class AntiCheatEvent extends Model
{
    protected $table = 'anticheat_event';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'game_id',
        'rule_type',
        'rule_name',
        'severity',
        'score_delta',
        'action',
        'evidence',
        'round_id',
        'stat_date',
        'status',
        'reviewer_id',
        'review_note',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'int',
        'game_id' => 'int',
        'severity' => 'int',
        'score_delta' => 'int',
        'reviewer_id' => 'int',
        'stat_date' => 'date',
        'created_at' => 'datetime',
    ];
}
