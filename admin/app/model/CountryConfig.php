<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class CountryConfig extends Model
{
    protected $table = 'country_config';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'country_code',
        'currency',
        'payment_methods',
        'withdraw_methods',
        'min_deposit',
        'status',
    ];

    protected $casts = [
        'min_deposit' => 'string',
        'status' => 'int',
    ];

    /**
     * 根据国家代码获取配置
     */
    public static function getByCode(string $code): ?self
    {
        return self::where('country_code', $code)->first();
    }
}
