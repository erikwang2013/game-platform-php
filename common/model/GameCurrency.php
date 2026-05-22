<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class GameCurrency extends Model
{
    protected $table = 'erik_game_currency';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'game_id',
        'name',
        'symbol',
        'exchange_rate',
        'spread_pct',
        'min_exchange',
        'max_exchange',
    ];

    protected $casts = [
        'exchange_rate' => 'string',
        'spread_pct' => 'string',
        'min_exchange' => 'string',
        'max_exchange' => 'string',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
