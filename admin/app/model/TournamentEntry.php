<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class TournamentEntry extends Model
{
    protected $table = 'erik_tournament_entry';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['tournament_id', 'user_id', 'score', 'rank'];
    protected $casts = ['score' => 'string', 'rank' => 'int'];

    public function tournament() { return $this->belongsTo(Tournament::class, 'tournament_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
