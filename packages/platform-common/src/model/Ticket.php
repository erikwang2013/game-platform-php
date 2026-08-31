<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Ticket extends Model
{
    protected $table = 'ticket';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id', 'type', 'subject', 'content', 'status',
        'priority', 'assigned_to', 'resolved_at',
    ];

    protected $casts = [
        'status' => 'string',
        'priority' => 'int',
        'resolved_at' => 'datetime',
    ];

    public function replies()
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
