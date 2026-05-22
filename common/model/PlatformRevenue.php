<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class PlatformRevenue extends Model
{
    protected $table = 'erik_platform_revenue';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'source',
        'game_id',
        'amount',
        'currency',
        'count',
        'remark',
    ];

    protected $casts = [
        'amount' => 'string',
        'count' => 'int',
    ];
}
