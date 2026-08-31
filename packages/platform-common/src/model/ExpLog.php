<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class ExpLog extends Model
{
    protected $table = 'exp_log';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['user_id', 'amount', 'source', 'ref_type', 'ref_id'];
    protected $casts = ['amount' => 'int'];
}
