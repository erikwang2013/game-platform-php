<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class Friend extends Model
{
    protected $table = 'erik_friend';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['user_id', 'friend_id', 'status'];
    protected $casts = ['status' => 'string'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friendUser()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}
