<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 账号-账号关联边（same_device / same_ip / referral / shared_phone）
 */
class AccountAccountLink extends Model
{
    protected $table = 'account_account_link';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id_a',
        'user_id_b',
        'link_type',
        'created_at',
    ];
}
