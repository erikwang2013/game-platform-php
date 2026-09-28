<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

/**
 * bcmath 通用数学（团队规范入口）。
 *
 * 价格/金额/精确数据计算一律使用 bcmath 扩展函数（bcmul/bcdiv/bcadd/bcsub），
 * 禁止 float/double 参与金额运算。注意 bcadd/bcmul/bcdiv 均为截断不舍入，
 * 需要四舍五入时必须经本类 round()（十进制半进位，与 PHP round() 一致）。
 */
final class BcMath
{
    /**
     * 绝对值（去掉前导负号，scale 不变）。
     *
     * 专门给「|a - b|」场景用：PHP 扩展没有 bcabs()，裸写 bcabs(bcsub(...)) 会抛
     * 「Call to undefined function」，且因 bcabs 命名太过自然而不易在评审中发现。
     * 本方法不做任何运算，输入原样（除符号）返回，故不参与四舍五入。
     */
    public static function abs(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }

    /** 十进制四舍五入到 $scale 位小数（负数按远离零进位） */
    public static function round(string $value, int $scale = 0): string
    {
        $neg    = str_starts_with($value, '-');
        $abs    = $neg ? substr($value, 1) : $value;
        $half   = '0.' . str_repeat('0', $scale) . '5';
        $result = bcadd($abs, $half, $scale);
        return $neg ? bcsub('0', $result, $scale) : $result;
    }

    /**
     * 百分比 = $numerator / $denominator × 100，十进制四舍五入到 $scale 位小数。
     * 注意：调用方须保证分母非零（bcdiv 除零抛 DivisionByZeroError，应如零值走兜底分支）。
     */
    public static function percent(string $numerator, string $denominator, int $scale = 2): string
    {
        return self::round(bcdiv(bcmul($numerator, '100', $scale + 4), $denominator, $scale + 4), $scale);
    }
}
