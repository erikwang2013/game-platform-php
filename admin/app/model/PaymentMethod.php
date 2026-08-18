<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;
use Erikwang2013\Encryptable\Encryptable;

class PaymentMethod extends Model
{
    protected $table = 'payment_method';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'type',
        'provider',
        'config',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
        'config' => Encryptable::class,
    ];
}
