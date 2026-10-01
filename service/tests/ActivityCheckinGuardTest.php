<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\service\ActivityService;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * `checkin` 端点不得把**事件驱动**的活动类型当成签到用。
 *
 * 缺陷形状（2026-10-01 由 C 端 react 那路读源码报出、我逐行复核并修）：
 * `ActivityService::checkin()` 传的 ctx 是 `['event' => '', 'game_id' => 0, …]`，而
 * `DailyTaskHandler::canJoin()` 原先**完全不看 `ctx['event']`**（只看 status／时间窗／game_id），
 * 于是加一个 `game_id=0` 的每日任务（**admin 新建时的默认值**）后点一次 checkin：
 *
 *   建行 target=1 → `current += 1` → `1 >= 1` → COMPLETED → grantRewards（:312 置 REWARDED）
 *
 * ⇒ **不需要做任何任务，点一下白拿奖**。更重的是第二层：该行随即变成 REWARDED，而
 * `ActivityService::progress()` 对 REWARDED **直接 return**（注释「本周期已发奖，不再累加」）
 * ⇒ **当天真实的 `deposit.completed` 事件再也累加不进去**，用户当天进度被永久锁死。
 *
 * 根因是 `checkin` **绕过了 `onProgress()` 的事件匹配**（后者本来就按 `task['event'] === $ctx['event']`
 * 判定，见 `DailyTaskHandler`）。修法是补一道与 handler 自身语义一致的闸：**没有事件名就不许参与**。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class ActivityCheckinGuardTest extends TestCase
{
    private static bool $booted = false;

    private int $activityId = 0;
    private int $userId = 0;

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

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();

        // target=2：**故意让一次事件不足以发奖** —— 正控只验「事件仍能累加」，
        // 不把用例推进 grantRewards/钱包那条资金路径。
        $this->activityId = SnowflakeService::generate();
        Db::table('activity')->insert([
            'id'     => $this->activityId,
            'type'   => 'daily_task',
            'name'   => 'guard-probe',
            'game_id' => 0,
            'config' => json_encode([
                'tasks' => [[
                    'event'  => 'deposit.completed',
                    'target' => 2,
                    'reward' => ['type' => 'platform_coin', 'amount' => '5'],
                ]],
            ]),
            'status' => 1,
        ]);

        $this->userId = SnowflakeService::generate();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        parent::tearDown();
    }

    private function participation(): ?object
    {
        return Db::table('activity_participation')
            ->where('user_id', $this->userId)
            ->where('activity_id', $this->activityId)
            ->first();
    }

    /**
     * ① 事件驱动的活动类型不接受 checkin —— 且**一行都不该落库**。
     *
     * 只断言「抛异常」不够：还得断言**没有副作用**，否则「先建行再抛」也会绿。
     */
    #[Test]
    public function checkinOnDailyTaskIsRejectedWithoutSideEffects(): void
    {
        $threw = false;
        try {
            ActivityService::checkin($this->userId, $this->activityId);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, '每日任务被 checkin 放行了 ⇒ 点一下即可白拿奖并锁死当天进度');
        $this->assertNull($this->participation(), '被拒的 checkin 仍落了行（副作用没挡住）');
    }

    /**
     * ② 正控：**真实事件**那条路不受影响 —— 否则「拒掉 checkin」也可能是把整条路一起弄死了。
     *
     * 断言 `current=1`（而不是只有一行）：证明事件确实被 `onProgress` 匹配并累加了。
     */
    #[Test]
    public function realEventStillAccumulatesProgress(): void
    {
        ActivityService::progress($this->userId, \common\model\Activity::find($this->activityId), [
            'event'   => 'deposit.completed',
            'game_id' => 0,
            'now'     => date('Y-m-d H:i:s'),
        ]);

        $row = $this->participation();
        $this->assertNotNull($row, '真实事件没能建出参与行 ⇒ 闸把正常路径也挡了');
        $this->assertSame(1, (int) $row->current, '真实事件没有累加进度');
    }
}
