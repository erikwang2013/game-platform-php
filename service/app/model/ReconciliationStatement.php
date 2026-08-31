<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReconciliationStatement extends Model
{
    protected $table = 'reconciliation_statement';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'gateway',
        'external_id',
        'amount',
        'currency',
        'status',
        'transaction_time',
        'local_order_id',
        'matched',
        'raw',
    ];

    protected $casts = [
        'amount' => 'string',
        'transaction_time' => 'datetime',
        'raw' => 'array',
        'matched' => 'integer',
        'created_at' => 'datetime',
    ];
}
