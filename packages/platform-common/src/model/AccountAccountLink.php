<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

/**
 * 账号-账号关联边（same_device / same_ip / referral / shared_phone）
 */
class AccountAccountLink extends Model
{
    protected $table = 'account_account_link';

    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false; // 该表只有 created_at（无 updated_at），Eloquent 写 updated_at 必 Unknown column；created_at 由 DDL 默认值兜

    protected $fillable = [
        'user_id_a',
        'user_id_b',
        'link_type',
        'created_at',
    ];
}
