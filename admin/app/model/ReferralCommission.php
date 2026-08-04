<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class ReferralCommission extends Model
{
    protected $table = 'erik_referral_commission';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['referral_id', 'user_id', 'level', 'source_user_id', 'source_amount', 'commission_rate', 'commission_amount', 'source_type', 'source_id'];
    protected $casts = ['level' => 'int', 'source_amount' => 'string', 'commission_rate' => 'string', 'commission_amount' => 'string'];
}
