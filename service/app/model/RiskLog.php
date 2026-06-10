<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class RiskLog extends Model
{
    protected $table = 'risk_log';

    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'rule_id',
        'type',
        'action',
        'context',
        'result',
    ];
}
