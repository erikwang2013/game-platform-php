<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Tournament extends Model
{
    protected $table = 'tournament';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'slug', 'type', 'description', 'game_id', 'start_at', 'end_at', 'prize_pool', 'entry_fee', 'max_players', 'status'];
    protected $casts = ['prize_pool' => 'string', 'entry_fee' => 'string', 'max_players' => 'int', 'status' => 'int', 'start_at' => 'datetime', 'end_at' => 'datetime'];

    public function entries() { return $this->hasMany(TournamentEntry::class, 'tournament_id'); }
    public function game() { return $this->belongsTo(Game::class, 'game_id'); }
}
