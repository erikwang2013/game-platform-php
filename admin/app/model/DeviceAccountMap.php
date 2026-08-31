<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 设备-账号关联边（图谱主表）
 */
class DeviceAccountMap extends Model
{
    protected $table = 'device_account_map';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'fp_hash',
        'user_id',
        'first_seen_at',
        'last_seen_at',
    ];
}
