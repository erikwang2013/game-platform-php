<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class Message extends Model
{
    protected $table = 'message';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['from_user_id', 'to_user_id', 'content', 'is_read'];
    protected $casts = ['is_read' => 'int'];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
