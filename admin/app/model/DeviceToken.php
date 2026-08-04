<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class DeviceToken extends Model
{
    protected $table = 'erik_device_token';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['user_id', 'platform', 'token'];
    protected $casts = ['platform' => 'string'];
}
