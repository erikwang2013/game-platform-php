<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class RiskCluster extends Model
{
    protected $table = 'game_risk_cluster';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'type',
        'fingerprint',
        'member_ids',
        'user_count',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_count' => 'int',
        'status' => 'int',
    ];
}
