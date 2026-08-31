<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Achievement extends Model
{
    protected $table = 'achievement';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['key', 'name', 'description', 'icon', 'condition_json', 'points'];
    protected $casts = ['points' => 'int'];
}
