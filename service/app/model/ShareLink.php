<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ShareLink extends Model
{
    protected $table = 'share_link';

    public $incrementing = false;
    protected $keyType = 'int';

    // 表无 updated_at 列
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'activity_id',
        'short_code',
        'clicks',
        'conversions',
        'expires_at',
    ];

    protected $casts = [
        'clicks' => 'int',
        'conversions' => 'int',
    ];
}
