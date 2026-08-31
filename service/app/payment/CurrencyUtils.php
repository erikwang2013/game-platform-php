<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

/**
 * 币种工具：零小数币种清单全局唯一维护（ISO 4217），
 * 避免 Stripe/Adyen/其他网关各自复制一份产生漂移。
 */
class CurrencyUtils
{
    /** ISO 4217 零小数币种：金额不放大 100 倍 */
    public const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW',
        'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true);
    }

    /** 金额转最小单位，零小数币种不放大 */
    public static function toMinor(string $amount, string $currency): string
    {
        if (self::isZeroDecimal($currency)) {
            return bcmul($amount, '1', 0);
        }
        return bcmul($amount, '100', 0);
    }

    /** 最小单位转回金额字符串（4 位小数精度） */
    public static function fromMinor(string $amount, string $currency): string
    {
        if (self::isZeroDecimal($currency)) {
            return $amount;
        }
        return bcdiv($amount, '100', 4);
    }
}
