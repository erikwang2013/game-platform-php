<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

/**
 * 活动参与进度。uk(user_id, activity_id, period_key) 幂等：
 * 同用户同活动同周期仅一条进度，重复上报累加不重复建行。
 */
class ActivityParticipation extends Model
{
    protected $table = 'activity_participation';

    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['id', 'user_id', 'activity_id', 'period_key', 'current', 'target', 'status', 'completed_at'];
    protected $casts = [
        'user_id'     => 'int',
        'activity_id' => 'int',
        'current'     => 'int',
        'target'      => 'int',
    ];

    public const STATUS_PROGRESSING = 'progressing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REWARDED = 'rewarded';
}
