<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class CountryConfig extends Model
{
    protected $table = 'erik_country_config';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'currency_symbol',
        'language',
        'timezone',
        'payment_methods',
        'status',
    ];

    protected $casts = [
        'payment_methods' => 'json',
        'status' => 'int',
    ];
}
