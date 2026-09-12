<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class CountryConfig extends Model
{
    protected $table = 'country_config';

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'country_code',
        'lang_prefix',
        'currency',
        'payment_methods',
        'withdraw_methods',
        'min_deposit',
        'max_deposit',
        'daily_deposit_limit',
        'withdraw_fee_percent',
        'withdraw_min',
        'settlement_days',
        'status',
    ];

    protected $casts = [
        'min_deposit' => 'string',
        'max_deposit' => 'string',
        'daily_deposit_limit' => 'string',
        'withdraw_fee_percent' => 'string',
        'withdraw_min' => 'string',
        'settlement_days' => 'int',
        'status' => 'int',
    ];

    /**
     * 根据国家代码获取配置
     */
    public static function getByCode(string $code): ?self
    {
        return self::where('country_code', $code)->first();
    }

    /**
     * 解析支付/提现方式 JSON（兼容旧数组 ["stripe"] 与新规则对象 {"stripe":{...}}），返回方法名列表
     */
    public static function methodNames(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_is_list($decoded) ? $decoded : array_keys($decoded);
    }

    /**
     * 语言前缀映射国家（用于本国支付优先），查 game_country_config.lang_prefix 表。
     * 迁移前的硬编码映射：zh->CN ja->JP ko->KR pt->BR hi->IN de->DE en->US。
     *
     * 返回空串仅表示"该语言无映射"（含空语言）；数据库查询失败一律向上抛出 \Throwable。
     * 二者必须可区分：调用方据此决定重试/回退，而不是把 DB 故障当成"无国家"继续走支付与合规链路。
     */
    public static function fromLang(string $lang): string
    {
        $prefix = strtolower(substr(trim($lang), 0, 2));
        if ($prefix === '') {
            return '';
        }
        return (string) (self::where('lang_prefix', $prefix)->value('country_code') ?? '');
    }
}
