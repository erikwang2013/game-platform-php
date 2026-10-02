<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\CouponController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * `CouponController::available()` 的查询次数**不随候选券数增长**。
 *
 * 被钉的代码：`user_limit` 的「我已领几张」。原实现是在 filter 闭包内逐张券
 * `UserCoupon::where(user_id, coupon_id)->count()` —— 即 N+1，而 :48 的注释宣称
 * 「按整批预取一次，避免逐张券各查一次」（**注释与实现相反**）。现在计数并进
 * `buildConditionContext()`：一次 `whereIn('coupon_id', …)->groupBy()->pluck()`。
 *
 * ⚠ 现有用例（CouponAvailableConditionsTest）测的是**筛选结果**，对「多查了几次」零灵敏 ——
 * 退化成 N+1 时结果集完全一样、那些用例全绿。故这里量的是**查询条数**：
 * 同一用户下先跑 3 张券、再跑 8 张（前 3 张还在），两次读数必须**相等**。
 * 分母固定项（充值聚合、game_id 去重等）两次一样，唯一会变的就是「每张券各查一次」那一项。
 *
 * ⚠ 绝对条数还带一条宽松上界（≤8）：本机真库若已被别的用例塞了带 conditions 的券，
 * 固定项会抬上去，故上界只用来抓「量级明显不对」，**等值断言才是主判据**。
 */
final class CouponAvailableQueryCountTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $seeded = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        HashidsBootstrap::start(null);
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

        $this->userId = (int) SnowflakeService::generate();
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
    public function queryCountDoesNotGrowWithCouponCount(): void
    {
        $small = $this->queriesForAvailable(3);
        $large = $this->queriesForAvailable(8);

        $this->assertSame($small, $large,
            "available() 的查询条数随候选券数增长（3 张券 {$small} 次 → 8 张券 {$large} 次）："
            . 'user_limit 的计数退回逐张券 count()＝N+1。');
        $this->assertLessThanOrEqual(8, $large,
            "available() 每请求查询条数 {$large} 超出预取口径（预期 ≤8：候选券 + user_coupon 计数 + 至多两条充值聚合）");
    }

    /** 播种 $total 张「本人可领」的券（累计），跑一次 available()，返回查询条数 */
    private function queriesForAvailable(int $total): int
    {
        while ($this->seeded < $total) {
            $this->seedCoupon();
        }

        $request = new Request("GET /api/v1/coupon/available HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->userId = $this->userId;

        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $body = json_decode(
                (string) (new CouponController())->available($request)->rawBody(),
                true
            ) ?? [];
            $count = count($connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
        }

        $this->assertSame(0, $body['code'] ?? -1,
            'available() 未成功，查询条数读数无意义：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $count;
    }

    /** 候选条件：status=1、无时间窗、total_qty=0（不限量）、user_limit=2、无条件 ⇒ 必然出现在列表里 */
    private function seedCoupon(): void
    {
        $id = (int) SnowflakeService::generate();
        $this->seeded++;

        Db::table('coupon')->insert([
            'id'         => $id,
            'name'       => 'QC ' . $id,
            'type'       => 'fixed',
            'value'      => '5.0000',
            'conditions' => null,
            'total_qty'  => 0,
            'used_qty'   => 0,
            'user_limit' => 2,
            'status'     => 1,
        ]);
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
