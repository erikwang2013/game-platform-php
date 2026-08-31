<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReconciliationDiff extends Model
{
    protected $table = 'reconciliation_diff';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'statement_id',
        'local_order_id',
        'diff_type',
        'severity',
        'description',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'statement_id' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
