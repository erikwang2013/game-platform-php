<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\service\AchievementService;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Db;

/**
 * `AchievementService::metricSum()` 的取整语义：**floor**、且不经过 float。
 *
 * 原实现是 `(int) floor((float) $query->sum($sumColumn))` —— 金额域（DECIMAL 列）唯一的 float 违规。
 * `sum_column` 由 admin 的 conditions JSON 指定，而 `normalizeTable()`（:166-174）只剥 `game_` 前缀、
 * **不是白名单** ⇒ 可以是任意列（`transaction.amount` 本身就带负号：earn 正 / spend 负）。
 *
 * 改成 bcmath 后要保住原来的两件事，这里一个用例一条：
 *  ① `bcdiv(…, 0)` 是**朝零**截断，而原式是 floor ⇒ 负数带小数时差 1（-1.9 应为 -2，不是 -1）；
 *  ② 空结果集经 Eloquent `sum()` 回的是 **int 0**（不是 null、也不是字符串）⇒ 那条路不能进 bcmath
 *     （`bcdiv(0, …)` 抛 TypeError）。原写法 `(float)` 顺手吞掉了它，改完若只写 `bcdiv($sum,…)` 就会红。
 *
 * 断言数值用 `assertSame(int)`: 端到端的进度值本就是 int（`computeProgress(): int`）。
 */
final class AchievementMetricSumFloorTest extends TestCase
{
    private static bool $booted = false;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function floorsPositiveAndNegativeSumsWithoutFloat(): void
    {
        $positive = $this->seedAmounts(['1.90000000', '0.50000000']);   // 2.4 ⇒ 2
        $negative = $this->seedAmounts(['-1.90000000']);               // -1.9 ⇒ **-2**（朝零截断会给 -1）
        $empty    = (int) SnowflakeService::generate();                 // 无行 ⇒ sum() 回 int 0

        $this->assertSame(2, $this->metricSum($positive), '正数小数应向下取整（floor）');
        $this->assertSame(-2, $this->metricSum($negative),
            '负数必须 floor 到更小：bcdiv(…, 0) 是朝零截断，直接用它会把 -1.9 算成 -1');
        $this->assertSame(0, $this->metricSum($empty),
            '空结果集时 Eloquent sum() 回 int 0（不是 null）⇒ 不能进 bcmath（bcdiv 收 int 抛 TypeError）');
    }

    /**
     * 金额域禁 float 的**可观测**判据。
     *
     * 上一条用例在 2.4 / -1.9 / 0 / 12346 上跑：那些量级下 `(int) floor((float) …)` 与 bcmath **同解**
     * ⇒ 拿它当「禁 float」的判据是假绿（变异实测：把实现换回 float 路径，上一条用例全绿）。
     * float 的失效要越过有效位才看得见：`floor((float)……)` 会把小数部分**四舍五入抬过整数边界**。
     *
     * `transaction.amount` 是 DECIMAL(20,8)（install.sql:327，12 位整数 + 8 位小数），
     * 实测量级门槛在 10 位整数附近：sum = 1000000000.99999999 时
     * 　float 路径 ⇒ 1000000001（错），bcmath 路径 ⇒ 1000000000（对）。
     * （同一现象在 DECIMAL(18,4) 上要 14 位整数才出现：99999999999999.9999 ⇒ 1.0E+14 ⇒ `(int)` 饱和。）
     */
    #[Test]
    public function hugeSumsDoNotLoseTheIntegerPartToFloat(): void
    {
        $user = $this->seedAmounts(['999999999.99999999', '1.00000000']);   // 和 = 1000000000.99999999

        $this->assertSame(1000000000, $this->metricSum($user),
            'sum 越过 float 有效位后，(float) 会把小数部分抬过整数边界 ⇒ 成就进度凭空多 1'
            . '（这正是金额域禁 float 的实质后果，不是风格问题）');
    }

    #[Test]
    public function integralSumsAreExact(): void
    {
        $user = $this->seedAmounts(['12345.00000000', '1.00000000']);

        $this->assertSame(12346, $this->metricSum($user), '整数值不得因取整路径而变化');
    }

    // ---------------------------------------------------------------- 夹具 / 调用

    /** @param string[] $amounts */
    private function seedAmounts(array $amounts): int
    {
        $userId = (int) SnowflakeService::generate();
        foreach ($amounts as $amount) {
            Db::table('transaction')->insert([
                'id'            => SnowflakeService::generate(),
                'user_id'       => $userId,
                'type'          => 'game_earn',
                'amount'        => $amount,
                'balance_after' => $amount,
            ]);
        }

        return $userId;
    }

    /** 真源：private static metricSum(int $userId, array $condition) */
    private function metricSum(int $userId): int
    {
        return (new ReflectionMethod(AchievementService::class, 'metricSum'))->invoke(
            null,
            $userId,
            // sum_column 刻意取**带符号**的 amount（不是默认的 platform_amount，后者不在 transaction 表上）：
            // 这正是 normalizeTable() 只剥前缀、不是白名单的那条路
            ['table' => 'transaction', 'sum_column' => 'amount']
        );
    }

    /** 与 14 个真库用例同一口径：先烧掉开发库那次 init 的守卫，再由测试库 Capsule 最后 setAsGlobal() 落笔 */
    private static function bootTargetDatabase(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        class_exists(Db::class);

        $conf = config('database');
        $name = $conf['default'];
        $conn = $conf['connections'][$name];

        $conn['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
        $user = getenv('GP_DB_USER');
        $pass = getenv('GP_DB_PASS');
        $conn['username'] = $user !== false && $user !== '' ? $user : $conn['username'];
        $conn['password'] = $pass !== false && $pass !== '' ? $pass : (string) $conn['password'];

        $capsule = new Capsule();
        $capsule->addConnection($conn, $name);
        $capsule->getDatabaseManager()->setDefaultConnection($name);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
