<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\BaseController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 订单号生成（BaseController::generateOrderNo / orderNoSuffix）。
 *
 * 覆盖背景：充值（DEP）与提现（WTH）两个控制器各自内联拼串，全仓零测试。
 * order_no 上有唯一键 uk_order_no：同一秒内撞号 = 插入失败 = 用户下单 500，
 * 而「同秒不同号」完全由熵后缀保证 —— 这正是必须钉住的契约。
 *
 * 另注意 strtoupper(...) 对 uniqid(_, true) 的后 6 位是空操作：该 6 位恒为十进制数字
 * （源码注释写「random 4 digits」已与实现漂移，本文件不改行为、只钉现状）。
 */
class OrderNoFormatTest extends TestCase
{
    private const TIMESTAMP = 1735689600; // 2025-01-01 00:00:00 UTC（远离各时区夏令时切换日）

    private static function build(string $prefix, int $timestamp, string $suffix): string
    {
        return (new ReflectionMethod(BaseController::class, 'generateOrderNo'))
            ->invoke(null, $prefix, $timestamp, $suffix);
    }

    private static function suffix(): string
    {
        return (new ReflectionMethod(BaseController::class, 'orderNoSuffix'))->invoke(null);
    }

    /**
     * 三段拼接结构：前缀 + 14 位时间 + 大写后缀，无分隔符，总长 23。
     * 中段用「反解回时间戳」校验而不是在测试里再写一遍 date('YmdHis') —— 后者只是把
     * 实现抄一遍，这里要求它真的是该时间戳的 YmdHis（任一时区下都成立）。
     */
    #[Test]
    public function orderNoIsPrefixPlusTimestampPlusUppercasedSuffix(): void
    {
        foreach (['WTH', 'DEP'] as $prefix) {
            $orderNo = self::build($prefix, self::TIMESTAMP, 'abc123');

            $this->assertSame(23, strlen($orderNo), '前缀 3 + 时间 14 + 后缀 6');
            $this->assertSame($prefix, substr($orderNo, 0, 3));
            $this->assertMatchesRegularExpression('/^\d{14}$/', substr($orderNo, 3, 14), '中段恒为 14 位数字');
            $this->assertSame('ABC123', substr($orderNo, 17), '后缀被转为大写');
            $this->assertSame(self::TIMESTAMP, strtotime(substr($orderNo, 3, 14)), '中段可反解回注入的时间戳');
        }
    }

    /** 时间戳边界：epoch 0 / 32 位上限 / 2038 之后（date() 在这些点不得抛错或截断） */
    #[Test]
    public function timestampBoundariesRoundTrip(): void
    {
        foreach ([0, 1, 2147483647, 4102444800] as $ts) {
            $orderNo = self::build('DEP', $ts, '460143');

            $this->assertSame(23, strlen($orderNo));
            $this->assertSame($ts, strtotime(substr($orderNo, 3, 14)), "ts={$ts} 反解不一致");
        }
    }

    /** 熵后缀形制：恒为 6 位、全十进制数字 */
    #[Test]
    public function suffixIsSixDecimalDigits(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $suffix = self::suffix();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $suffix, "第 {$i} 次取到 [{$suffix}]");
        }
    }

    /**
     * 唯一性（order_no 唯一键的根防线）：同一秒内连取 1000 个，重复数必须 < 1%。
     *
     * 阈值的依据：后缀空间 10^6，1000 次抽样的生日碰撞期望 ≈ 0.5 次（实测 998~1000 个不同值）。
     * 阈值放到 990 是为了不因随机波动假红；但任何「后缀变成常量/秒级/固定位数不足」的改动
     * 都会让它掉到个位数甚至 1，本用例即失败。
     */
    #[Test]
    public function suffixKeepsOrderNumbersApartWithinTheSameSecond(): void
    {
        $ts = time();
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $seen[self::build('WTH', $ts, self::suffix())] = true;
        }

        $this->assertGreaterThanOrEqual(990, count($seen), '同秒 1000 单的重复数超过 1%');
    }
}
