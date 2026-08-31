<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReconciliationBatch extends Model
{
    protected $table = 'reconciliation_batch';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'gateway',
        'date_range_start',
        'date_range_end',
        'status',
        'error_msg',
        'total_statements',
        'matched_count',
        'diff_count',
    ];

    protected $casts = [
        'total_statements' => 'integer',
        'matched_count' => 'integer',
        'diff_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function statements()
    {
        return $this->hasMany(ReconciliationStatement::class, 'batch_id');
    }

    public function diffs()
    {
        return $this->hasMany(ReconciliationDiff::class, 'batch_id');
    }
}
