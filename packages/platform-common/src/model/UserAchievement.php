<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class UserAchievement extends Model
{
    protected $table = 'user_achievement';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['user_id', 'achievement_id', 'progress', 'completed'];
    protected $casts = ['progress' => 'int', 'completed' => 'int'];
}
