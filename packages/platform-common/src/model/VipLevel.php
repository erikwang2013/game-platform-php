<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class VipLevel extends Model
{
    protected $table = 'vip_level';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['level', 'name', 'required_exp', 'benefits'];
    protected $casts = ['level' => 'int', 'required_exp' => 'int'];
}
