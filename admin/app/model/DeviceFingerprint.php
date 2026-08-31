<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 设备指纹（只存 hash，不存明文 UA / IP）
 */
class DeviceFingerprint extends Model
{
    protected $table = 'device_fingerprint';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'fp_hash',
        'ip_c_segment',
        'user_agent_hash',
        'accept_lang_hash',
        'account_count',
        'first_seen_at',
        'last_seen_at',
    ];
}
