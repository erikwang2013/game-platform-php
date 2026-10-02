<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\process\EventConsumer;
use PHPUnit\Framework\TestCase;

/**
 * Outbox 退避公式的判据钉（不连库 / 不连 Redis：反射常量 + 源文件文本）。
 *
 * `BACKOFF_PREDICATE`（SQL 侧，决定"这一行现在还轮不轮到"）与 `backoffSeconds()`
 * （PHP 侧，决定"还要等多久"）是同一公式的两处实现，漂移的两种方向都是静默的：
 *   谓词比 PHP 宽松 -> 未到期的行被反复取出，重试空转（退避形同虚设，日志只有重试噪声）；
 *   谓词比 PHP 严格 -> 行永远取不出来，outbox 消费饿死（更坏：没有报错，只是不再消费）。
 * 所以这里钉的不是某个具体秒数，而是两侧在**可达定义域**上给出同一答案。
 */
final class EventConsumerBackoffContractTest extends TestCase
{
    /** 第 n 次重试前的等待；用谓词里抽出的常量与算子表达（不引用被测常量，否则就成了自证） */
    private static function sqlWait(int $retryCount, int $base, int $sub, int $floor, int $max): int
    {
        return (int) min($base << max($retryCount - $sub, $floor), $max);
    }

    private static function const(string $name): int
    {
        $value = (new \ReflectionClass(EventConsumer::class))->getReflectionConstant($name)->getValue();
        self::assertIsInt($value, "EventConsumer::{$name} 必须是整数字面量：退避全程整数运算，不引入浮点");

        return $value;
    }

    /** 谓词整串锚定：形状、列名、算子、常量任一漂移都在这里红 */
    public function testPredicateShapeAndConstants(): void
    {
        $sql = EventConsumer::BACKOFF_PREDICATE;

        self::assertSame(1, preg_match(
            '/^\(retry_count = 0 OR TIMESTAMPDIFF\(SECOND, COALESCE\(updated_at, created_at\), NOW\(\)\) >= '
            . 'LEAST\((\d+) << GREATEST\(retry_count - (\d+), (\d+)\), (\d+)\)\)$/',
            $sql,
            $m
        ), '退避谓词形状漂移，两侧一致性无从核对：' . $sql);
        [, $base, $sub, $floor, $max] = $m;

        // 谓词串是常量拼出来的：常量与串里的数不等，说明有人把公式硬编码进去了
        self::assertSame(self::const('BACKOFF_BASE'), (int) $base, '谓词基数与 BACKOFF_BASE 不一致');
        self::assertSame(self::const('BACKOFF_MAX'), (int) $max, '谓词上界与 BACKOFF_MAX 不一致');

        self::assertSame(1, (int) $sub, '指数起点必须是 retry_count - 1：写成 retry_count 则第 1 次重试就要等 2×BASE');
        self::assertSame(0, (int) $floor, 'retry_count = 0 时位移必须被夹到 0：负位移在 PHP 里抛 ArithmeticError、在 MySQL 里退化成 0，两侧不同形');

        self::assertStringContainsString(' >= ', $sql, '改成 > 会让两侧在边界秒上差一秒（来回各一次即抖动重试）');
        self::assertStringContainsString('retry_count = 0 OR', $sql, '首投必须短路：否则新行要被判定"还没到退避窗口"而延迟 BASE 秒');
        self::assertStringNotContainsString('POW(', $sql, '位运算换 POW 会引入浮点与隐式转型，且破坏与 PHP 的逐位一致');
    }

    /** 主契约：可达定义域（drainBatch 的 where retry_count < MAX_ATTEMPTS）上，两侧给出同一个等待秒数 */
    public function testSqlAndPhpAgreeOnEveryReachableRetryCount(): void
    {
        [, $base, $sub, $floor, $max] = preg_match(
            '/LEAST\((\d+) << GREATEST\(retry_count - (\d+), (\d+)\), (\d+)\)\)/',
            EventConsumer::BACKOFF_PREDICATE,
            $m
        ) === 1 ? $m : [null, null, null, null, null];

        // 可达域由消费侧自己决定：查询条件是 retry_count < MAX_ATTEMPTS，超出这个范围的行根本取不出来。
        // 从 1 起：retry_count = 0 不走公式（见下）
        foreach (range(1, self::const('MAX_ATTEMPTS') - 1) as $retryCount) {
            self::assertSame(
                self::sqlWait($retryCount, (int) $base, (int) $sub, (int) $floor, (int) $max),
                EventConsumer::backoffSeconds($retryCount),
                "第 {$retryCount} 次重试：SQL 谓词与 backoffSeconds() 给出的等待不一致"
            );
        }

        // retry_count = 0 两侧不是同一个算式对齐的，而是靠谓词里的 `retry_count = 0 OR` 短路对齐：
        // PHP 侧返回 0（首投不等待），而公式在这一点上算出来是 BASE 秒。
        // 那个短路项因此是承重墙：删掉它，新行会被判「未到退避窗口」而空等 BASE 秒，两侧当场分叉。
        self::assertSame(0, EventConsumer::backoffSeconds(0), '首投等待必须为 0');
        self::assertSame(
            (int) $base,
            self::sqlWait(0, (int) $base, (int) $sub, (int) $floor, (int) $max),
            '公式在 retry_count = 0 处本就该算出 BASE 秒：这里若不是 BASE，说明公式形状已变，上面的短路论证作废'
        );
        self::assertStringContainsString('retry_count = 0 OR', EventConsumer::BACKOFF_PREDICATE, '首投短路项缺失 :: 新事件将被延迟 BASE 秒才第一次被消费');
    }

    /** 可达域之外：封顶是为了不溢出，不是为了让公式变形——单调不减且恒有界 */
    public function testBackoffStaysBoundedAndMonotonicBeyondReachableRange(): void
    {
        $max = self::const('BACKOFF_MAX');
        $previous = 0;

        for ($attempts = 1; $attempts <= 64; $attempts++) {
            $current = EventConsumer::backoffSeconds($attempts);

            // 去掉 `min($attempts - 1, 16)` 封顶后实测：n=62/63 得负数（负等待=立刻重试，退避失效）、
            // n>=64 得 0（同样立刻重试）——两条都会被下面两个断言抓住
            self::assertGreaterThanOrEqual($previous, $current, "第 {$attempts} 次重试的等待比上次短：重试越多次反而越急");
            self::assertLessThanOrEqual($max, $current, "第 {$attempts} 次重试突破退避上界：无界增长会掩盖住「一直失败」这件事");
            $previous = $current;
        }

        self::assertSame($max, EventConsumer::backoffSeconds(PHP_INT_MAX), '极大重试数必须稳定落到上界（位移溢出会让它变 0 或负数）');
    }

    /**
     * 同一常量的两处比较：查询下界与死信阈值必须互补，否则行会停在 pending 且永远取不出来。
     * 死信侧比较的是本地 $attempt（= 认领时读到的 retry_count + 1，见 drainBatch），
     * 不再直接读 $row->retry_count —— 认领后该属性故意停在认领前的版本号（写回会覆盖
     * 窗口过期后别处的新认领），读它会少报一次尝试。
     * 「$attempt 确实由认领版本号推出」由 EventConsumerRuntimeTest::testDeadLetterOnlyAfterMaxAttempts
     * 行为级钉住（三个可达 retry_count 逐个断言「先 +1、再判死信」）。
     */
    public function testRetryBoundAndDeadThresholdAreComplementary(): void
    {
        $source = dirname(__DIR__) . '/app/process/EventConsumer.php';
        self::assertFileExists($source, '消费进程文件缺失，契约无从核对');
        $code = (string) file_get_contents($source);

        self::assertStringContainsString("where('retry_count', '<', self::MAX_ATTEMPTS)", $code, '取行条件不再与 MAX_ATTEMPTS 绑定');
        self::assertStringContainsString('$attempt >= self::MAX_ATTEMPTS;', $code, '死信阈值不再与 MAX_ATTEMPTS 绑定');
        self::assertStringContainsString('self::BACKOFF_PREDICATE', $code, '谓词没被查询用上：钉住的公式与实际执行的谓词脱钩');
    }
}
