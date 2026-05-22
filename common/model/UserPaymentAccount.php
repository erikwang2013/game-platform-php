<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class UserPaymentAccount extends Model
{
    protected $table = 'erik_user_payment_account';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'type',
        'account_name',
        'account_info',
        'is_default',
        'is_verified',
    ];

    protected $casts = [
        'account_info' => Encryptable::class,
        'is_default' => 'int',
        'is_verified' => 'int',
    ];
}
