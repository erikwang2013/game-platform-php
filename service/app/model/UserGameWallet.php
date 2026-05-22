<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class UserGameWallet extends Model
{
    protected $table = 'erik_user_game_wallet';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'game_id',
        'currency_id',
        'balance',
        'frozen_balance',
    ];

    protected $casts = [
        'balance' => 'string',
        'frozen_balance' => 'string',
    ];
}
