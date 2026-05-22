<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class RiskRule extends Model
{
    protected $table = 'erik_risk_rule';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'type',
        'config',
        'action',
        'priority',
        'status',
    ];

    protected $casts = [
        'priority' => 'int',
        'status' => 'int',
    ];

    public static function getEnabled(): array
    {
        return static::where('status', 1)
            ->orderBy('priority', 'desc')
            ->get()
            ->all();
    }
}
