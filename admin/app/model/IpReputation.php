<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * IP 信誉（0=bad / 50=neutral / 100=good）
 */
class IpReputation extends Model
{
    protected $table = 'ip_reputation';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'ip',
        'reputation_score',
        'source',
        'hit_count',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'reputation_score' => 'int',
        'hit_count' => 'int',
    ];
}
