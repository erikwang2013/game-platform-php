<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WithdrawLimit extends Model
{
    protected $table = 'erik_withdraw_limit';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_level',
        'single_min',
        'single_max',
        'daily_limit',
        'monthly_limit',
        'fee_pct',
        'fee_max',
        'auto_approve_threshold',
    ];

    protected $casts = [
        'single_min' => 'string',
        'single_max' => 'string',
        'daily_limit' => 'string',
        'monthly_limit' => 'string',
        'fee_pct' => 'string',
        'fee_max' => 'string',
        'auto_approve_threshold' => 'string',
    ];

    public static function getByLevel(string $level): ?self
    {
        return static::where('user_level', $level)->first();
    }
}
