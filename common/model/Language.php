<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Language extends Model
{
    protected $table = 'erik_language';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'native_name', 'icon', 'status', 'sort'];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
    ];
}
