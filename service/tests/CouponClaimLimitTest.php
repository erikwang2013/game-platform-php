<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\CouponController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * 券领取的两条拒绝路径（限领 / 售罄）走真库真控制器。
 *
 * 动机：user_limit 的判读从「事务外 check-then-act」挪进了「事务内、锁券行之后」，
 * 两条失败原因经由闭包外的 $failReason 带出 —— 这种改动最容易把两句提示串味，
 * 或者把「被拒的那次也扣了库存」这种半写带回来。本用例钉住：
 *   - user_limit=1 第二次领取 → 400 + 限领那句，且不落第二行 user_coupon、不扣库存
 *   - 库存耗尽 → 400 + 售罄那句（不能串成限领那句），且库存只被扣一次
 *
 * 并发本身（同用户两请求同时通过计数）无法在本进程内确定性复现：真正的证据是
 * claim() 在事务内先对券行 lockForUpdate 再计数，见 CouponController::claim()。
 */
class CouponClaimLimitTest extends TestCase
{
    private static bool $booted = false;

    private int $couponId = 0;

    /** 本用例造过的用户 id，tearDown 只删这些人的券行 */
    private array $userIds = [];

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

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $userId) {
            Db::table('user_coupon')->where('user_id', $userId)->delete();
        }
        if ($this->couponId !== 0) {
            Db::table('coupon')->where('id', $this->couponId)->delete();
        }
    }

    #[Test]
    public function userLimitBlocksSecondClaimWithItsOwnMessage(): void
    {
        $userId = $this->seedCoupon(userLimit: 1, totalQty: 0);

        $first = $this->claim($userId);
        $this->assertSame(0, $first['code'], '首次领取应成功：' . json_encode($first));

        $second = $this->claim($userId);
        $this->assertSame(400, $second['code'], '超出限领应 400：' . json_encode($second));
        $this->assertSame(
            '您已达到该优惠券的领取上限',
            $second['message'],
            '超限必须是限领那句；串成售罄说明 $failReason 的判读被覆盖了'
        );

        $this->assertSame(1, $this->myCouponCount($userId), '限领 1 时只能落一行 user_coupon');
        $this->assertSame(1, $this->usedQty(), '被限领拒绝的那次不得扣库存');
    }

    #[Test]
    public function soldOutReportsItsOwnMessageAndChargesStockOnce(): void
    {
        $userId  = $this->seedCoupon(userLimit: 0, totalQty: 1);
        $otherId = $this->seedUser();

        $first = $this->claim($userId);
        $this->assertSame(0, $first['code'], '首张应领到：' . json_encode($first));
        $this->assertSame(1, $this->usedQty(), '成功一次库存 +1');

        $second = $this->claim($otherId);
        $this->assertSame(400, $second['code'], '库存耗尽应 400：' . json_encode($second));
        $this->assertSame(
            '优惠券已被领完',
            $second['message'],
            '售罄必须报售罄，不能串成限领那句'
        );

        $this->assertSame(1, $this->usedQty(), '库存只应被扣一次');
        $this->assertSame(0, $this->myCouponCount($otherId), '被拒的一方不得落券行');
    }

    /**
     * 建一张券并返回一个可用用户 id。
     *
     * @param int $userLimit 0 = 不限领
     * @param int $totalQty  0 = 不限量
     */
    private function seedCoupon(int $userLimit, int $totalQty): int
    {
        $this->couponId = SnowflakeService::generate();

        Db::table('coupon')->insert([
            'id'           => $this->couponId,
            'name'         => 'test-coupon-' . $this->couponId,
            'type'         => 'fixed',
            'value'        => '1.0000',
            'min_amount'   => '0.0000',
            'max_discount' => '0.0000',
            'conditions'   => null,
            'game_id'      => 0,
            'total_qty'    => $totalQty,
            'used_qty'     => 0,
            'user_limit'   => $userLimit,
            'start_at'     => null,
            'end_at'       => null,
            'status'       => 1,
        ]);

        return $this->seedUser();
    }

    private function seedUser(): int
    {
        $userId = SnowflakeService::generate();
        $this->userIds[] = $userId;

        return $userId;
    }

    /** @return array<string, mixed> 解码后的响应体 */
    private function claim(int $userId): array
    {
        $request = new Request("POST /api/v1/coupon/claim HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['coupon_id' => HashidsService::encode($this->couponId)]);
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId;

        $response = (new CouponController())->claim($request);
        $this->assertInstanceOf(Response::class, $response);

        return json_decode($response->rawBody(), true);
    }

    private function myCouponCount(int $userId): int
    {
        return (int) Db::table('user_coupon')
            ->where('user_id', $userId)
            ->where('coupon_id', $this->couponId)
            ->count();
    }

    private function usedQty(): int
    {
        return (int) Db::table('coupon')->where('id', $this->couponId)->value('used_qty');
    }

    /**
     * 让 support\Db 指向测试库。
     *
     * tests/bootstrap.php 只把测试库配置写进了一个局部数组；而 support\Db 首次被 autoload 时
     * 其文件尾部的 Webman\Database\Initializer::init(config('database')) 会再建一个 capsule
     * 并 setAsGlobal —— 用的是【开发库】的 config('database')。所以必须先把这次一次性初始化
     * 烧掉，再自己 setAsGlobal，否则查询打的是开发库。
     */
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
        // 凭据优先取环境变量（GP_DB_USER/GP_DB_PASS，本机可用其覆盖），缺省回落到 config('database')
        // 即 .env 的口令 —— 不要硬编码空串：那会让本类及其之后的所有用例连不上库并静默 skip。
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
