<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Transaction extends Model
{
    protected $table = 'transaction';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'scope',
        'game_id',
        'currency_id',
        'amount',
        'balance_after',
        'ref_type',
        'ref_id',
        'remark',
    ];

    protected $casts = [
        'amount' => 'string',
        'balance_after' => 'string',
    ];
}
