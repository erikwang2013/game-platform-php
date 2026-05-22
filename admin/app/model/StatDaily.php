<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class StatDaily extends Model
{
    protected $table = 'erik_stat_daily';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'stat_type',
        'game_id',
        'metrics',
    ];

    public static function getByDate(string $date, string $type, int $gameId = 0): ?self
    {
        $query = static::where('date', $date)
            ->where('stat_type', $type);

        if ($gameId > 0) {
            $query->where('game_id', $gameId);
        }

        return $query->first();
    }
}
