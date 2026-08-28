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
        'countries',
        'currency',
        'min_amount',
        'max_amount',
    ];

    protected $casts = [
        'status' => 'int',
        'sort' => 'int',
        'countries' => 'array',
        'min_amount' => 'string',
        'max_amount' => 'string',
        'config' => Encryptable::class,
    ];
}
