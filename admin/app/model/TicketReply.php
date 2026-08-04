<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TicketReply extends Model
{
    protected $table = 'erik_ticket_reply';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'user_id', 'content', 'is_admin'];

    protected $casts = ['is_admin' => 'int'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
