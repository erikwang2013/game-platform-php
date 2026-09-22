<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\process\Health;
use PHPUnit\Framework\TestCase;

/**
 * 提现完成事件对账的判据钉（不连库 / 不连 Redis：纯常量 + 文件系统判据）。
 *
 * 巡检的判据写歪了，整套对账就是摆设；而它歪掉的方式全都是静默假绿：
 *   1. INNER JOIN 取代 LEFT JOIN        -> 缺行被吃掉，gap 恒 0
 *   2. 再补一句 WHERE eo.event_id IS NULL -> 分母 total 一起归零，「无样本」与「已修复」不可分
 *   3. event_id 格式与生产者漂移         -> 恒 100% gap，告警疲劳，等于没有巡检
 * 1、2 由 SQL 结构断言钉住；3 靠与 PayoutService 的字面量对账（唯一无需连库的核对方式——
 * 生产者的 eventId 是方法体内的插值字符串，反射也读不到，只能读源文件）。
 */
final class WithdrawEventReconcileContractTest extends TestCase
{
    /** 生产者与消费者之间唯一的硬契约：event_id 的拼法 */
    public function testEventIdFormatMatchesProducer(): void
    {
        $producer = dirname(__DIR__, 2) . '/packages/platform-common/src/service/PayoutService.php';
        // 不 skip：文件没了或格式变了都是真问题，静默跳过等于放行漂移
        self::assertFileExists($producer, '生产者文件缺失，契约无从核对');

        // 生产者侧：EventPublisher::push('withdraw.completed', "withdraw_{$order->id}_completed", ...)
        self::assertSame(1, preg_match(
            '/"([^"$]*)\{\$(\w+)->(\w+)\}([^"$]*)"/',
            (string) file_get_contents($producer),
            $p
        ), 'PayoutService 未按 "前缀{列名}后缀" 拼 eventId，契约对账失效');
        $producerFormat = $p[1] . '{' . $p[3] . '}' . $p[4];

        // 消费者侧：CONCAT('withdraw_', wo.id, '_completed')
        self::assertSame(1, preg_match(
            "/CONCAT\(\s*'([^']*)'\s*,\s*\w+\.(\w+)\s*,\s*'([^']*)'\s*\)/",
            Health::GAP_SQL,
            $q
        ), '对账 SQL 未按 CONCAT(\'前缀\', 别名.列名, \'后缀\') 拼 event_id，契约对账失效');
        $consumerFormat = $q[1] . '{' . $q[2] . '}' . $q[3];

        self::assertSame($producerFormat, $consumerFormat, 'event_id 格式漂移 :: 对账将恒报 100% gap');
    }

    /** total / gap 语义：分母是 LEFT JOIN 前的订单集合，gap 是连接未命中 */
    public function testReconcileSqlKeepsTotalAndGapSemantics(): void
    {
        $sql = Health::GAP_SQL;

        self::assertStringContainsString('LEFT JOIN game_event_outbox', $sql, 'INNER JOIN 会吃掉缺行 :: gap 恒 0 假绿');
        self::assertStringContainsString('eo.event_id IS NULL', $sql, 'gap 必须由连接未命中推出（join 键也得是 event_id）');
        self::assertStringNotContainsString('WHERE eo.event_id IS NULL', $sql, '把 IS NULL 提到 WHERE 会把分母 total 一起归零');

        self::assertStringContainsString('COUNT(*) AS total', $sql, 'total 是分母：为 0 时结论未知，不能与「已修复」混淆');
        self::assertStringContainsString('AS gap', $sql);
        self::assertSame(2, substr_count($sql, '?'), '两个占位符须与 Db::select 的绑定（status、窗口天数）一一对应');
        self::assertStringContainsString('wo.status = ?', $sql);
        self::assertStringContainsString('wo.paid_at >=', $sql, '无时间窗口 :: 扫全表历史，窗口滚动归零的信号消失');
    }
}
