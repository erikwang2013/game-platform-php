<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class UserVip extends Model
{
    protected $table = 'user_vip';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['user_id', 'level', 'exp', 'total_exp'];
    protected $casts = ['level' => 'int', 'exp' => 'int', 'total_exp' => 'int'];
}
