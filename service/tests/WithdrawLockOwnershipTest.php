<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WithdrawController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Db;
use support\Redis;

/**
 * 提现串行锁的**属主语义**：值必须是每请求唯一的 token，finally 只删自己的那一把。
 *
 * 原实现是固定值 `'1'` + finally 里无条件 `Redis::del($lockKey)`。锁 TTL 15s，而一次申请
 * （风控 + 钱包 + 落单）可能超过 15s：第一个请求的锁过期后第二个请求拿到锁，第一个的 finally
 * 随即把**第二个的锁**删掉 ⇒ 第三个请求也进来 ⇒ 日/月限额的 check-then-act 又出现并发窗口。
 *
 * 钉两层，缺一不可：
 *  - **语义层**（反射直调 `releaseLockIfOwned`）：非属主时键必须还在、属主自己删得掉。
 *    端到端用例**看不见**这一层 —— 正常单线程流程里 finally 遇到的永远是自己设的 token，
 *    「无条件 del」与「比对后 del」读数完全一样。
 *  - **接线层**（真身 `apply()`）：token 传错 / finally 没执行 ⇒ 锁泄漏在 Redis 里，
 *    该用户 15s 内每次申请都被自己的锁挡成 429。这条让「反射测绿了但接线没接上」不可能发生。
 *
 * 端到端那条的落点是**金额校验驳回**（default 档 single_min=1.0000，请求 0.0001）：
 * 它在 applyLocked 里、风控检查与任何落库**之前**返回，因此本用例不产生资金副作用；
 * 而它已经过了全局开关 → 验证码 → 拿锁三道闸，finally 一定会跑。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 WithdrawDailyLimitBoundaryTest 的口径）。
 *
 * 并发隔离（三处共享状态各自的隔离手段；2026-10-02 按"并行跑套件会出假失败"复核）：
 *  - **锁键** `withdraw:apply:{userId}`：userId 是本用例现取的雪花 id ⇒ 键天然独占，不与任何
 *    用例/进程串场；tearDown 再显式 `del` 一次（15s TTL 不等于立刻释放，不能只靠过期）。
 *  - **全局开关行**：写入包在 setUp 的外层事务里，tearDown 整笔回滚 ⇒ **原值自动复原**、不留终态
 *    （不是"只写终态"）。⚠ `game_platform_config` 有 `uk_group_key(group,key)`：同期若有另一支
 *    进程也在插同一行，会等到本用例回滚为止；本用例事务窗口 <1s，实测未观察到阻塞。
 *  - **验证码**：走 CaptchaTestHelper 的按用例名分槽地址，不落进整套件共用的 `0.0.0.0` 桶；
 *    本文件每次运行只消耗 2 次校验，离 30 次/60 秒的配额很远。
 */
final class WithdrawLockOwnershipTest extends TestCase
{
    use CaptchaTestHelper;

    private static bool $booted = false;
    private static bool $redisOk = false;

    /** 本用例独占的用户 id ⇒ 锁键独占，不与并发套件/其它用例串场 */
    private int $userId = 0;

    /** 低于 default 档 single_min（1.0000）⇒ 走 applyLocked 的金额驳回分支 */
    private const REQUEST_AMOUNT = '0.0001';

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 金额驳回响应仍会走 json() 信封；hashids 绑定是 success 路径的依赖，统一起一次（同其余提现用例）
        HashidsBootstrap::start(null);

        try {
            Redis::setex('withdraw:lock:probe', 5, '1');
            Redis::del('withdraw:lock:probe');
            self::$redisOk = true;
        } catch (\Throwable $e) {
            self::$redisOk = false;
        }
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        if (!self::$redisOk) {
            $this->markTestSkipped('Redis 不可用（跳过锁用例）');
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

        // 事务回滚不回 Redis：锁键按本用例的用户 id 收尾，别留 15s 垃圾（同 LeaderboardUserIdEncodeTest）
        if ($this->userId > 0 && self::$redisOk) {
            Redis::del($this->lockKey());
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------ 语义层

    /**
     * 非属主不得删锁 —— 这是 ⑥ 的本体。
     *
     * 带**正证**：同一个键换成属主 token 后必须删得掉。否则「键还在」也可能只是这条路根本不删任何键
     * （例如有人把整个 Redis::eval 删了），单看非属主那条会假绿。
     */
    #[Test]
    public function foreignTokenIsNeverDeletedAndOwnTokenIs(): void
    {
        $key = $this->lockKey();
        Redis::set($key, 'owner-A', 'EX', 15);

        $this->releaseLockIfOwned($key, 'owner-B');

        $this->assertSame('owner-A', Redis::get($key),
            '锁已过期、别人拿到锁之后，本请求的 finally 把**别人的锁**删掉 ⇒ 第三个请求随即进来，'
            . '限额的 check-then-act 又出现并发窗口。非属主时必须一个键都不动。');

        $this->releaseLockIfOwned($key, 'owner-A');

        // 用 exists 而不是 `assertFalse(Redis::get())`：本驱动的 get 对缺失键回 **null**（实测），
        // 不是 false ⇒ 那种断言在「键没了」时反而红
        $this->assertSame(0, Redis::exists($key),
            '属主必须删得掉自己的锁：token 比对写成恒假（或 eval 被删掉）会锁泄漏 ⇒ 用户 15s 内一律 429');
    }

    /**
     * 键已不存在时是 no-op（锁自然过期后的正常路径）：不得抛异常。
     * 抛了会被 finally 的 catch 吞成 warning 日志 —— 但那是**静默降级**，这里要求它根本不抛。
     */
    #[Test]
    public function missingKeyIsANoOp(): void
    {
        $key = $this->lockKey();
        Redis::del($key);

        $this->releaseLockIfOwned($key, 'whatever');

        $this->assertSame(0, Redis::exists($key), '对不存在的键调用后不得反而造出一个键');
    }

    // ------------------------------------------------------------ 接线层

    /**
     * 前提探针 + 键格式校验：**别人持有的锁**在的时候，本请求被 429 挡回，且那把锁一个字节都不变。
     *
     * 这条同时锚住本用例用的键格式与控制器一致：若控制器改了键格式，这里不再命中，
     * 请求会一路走到 400（金额驳回）而不是 429 ⇒ 红，而不是下面那条「键没了」的假绿。
     */
    #[Test]
    public function aHeldLockRejectsTheRequestAndIsLeftUntouched(): void
    {
        $this->enableWithdrawSwitch();
        Redis::set($this->lockKey(), 'someone-else', 'EX', 15);

        $payload = $this->apply();

        $this->assertSame(429, $payload['code'] ?? -1,
            '已有人持锁时本请求必须被 429 挡回（拿不到锁＝按用户串行化的那道闸）'
            . '；不是 429 说明键格式与本用例不一致或锁没生效。实际响应：'
            . json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertSame('someone-else', Redis::get($this->lockKey()),
            '被挡回的请求不得删掉不属于它的锁');
    }

    /**
     * 走完一整趟真身流程后，锁必须已被释放。
     *
     * 这是唯一能咬住「token 传错」的读数：Lua 比对的是 ARGV[1]，传错（或传成常量）就删不掉，
     * 键会留在 Redis 里 —— 语义层那条反射用例对此完全无感（它直接喂正确 token）。
     */
    #[Test]
    public function applyReleasesItsOwnLock(): void
    {
        $this->enableWithdrawSwitch();

        $payload = $this->apply();

        // 前提探针：400 = 已过全局开关 → 验证码 → **拿到锁** → 走到 applyLocked 的金额驳回。
        // （拿不到锁是 429、Redis 故障是 503、验证码不过 422，都不会是 400。）
        $this->assertSame(400, $payload['code'] ?? -1,
            '应恰好落在 applyLocked 的金额驳回分支（低于 default 档 single_min=1.0000）：'
            . '它不是 400 说明本用例根本没走到锁之后的路径，下面的断言会变成恒真。实际响应：'
            . json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('minimum withdrawal limit', (string) ($payload['message'] ?? ''),
            '驳回原因应是单笔最小额，而不是别的 400 分支：' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $this->assertSame(0, Redis::exists($this->lockKey()),
            'apply() 的 finally 必须删掉**自己刚拿的那把锁**；键还在＝token 与 set 时用的不一致'
            . '（或删除逻辑没执行）⇒ 该用户 15s 内每次申请都被自己的锁挡成 429');
    }

    // ------------------------------------------------------------ 夹具

    /** 与控制器 apply() 同源：`withdraw:apply:{userId}` */
    private function lockKey(): string
    {
        return "withdraw:apply:{$this->userId}";
    }

    /** 反射直调 private static releaseLockIfOwned(string $key, string $token) */
    private function releaseLockIfOwned(string $key, string $token): void
    {
        (new ReflectionMethod(WithdrawController::class, 'releaseLockIfOwned'))->invoke(null, $key, $token);
    }

    /**
     * 全局开关：`apply()` 的第一道闸，为假时直接 403、连锁都不会拿。
     * 测试库的 platform_config 没有 withdraw/global_switch 行（只有 min_amount/daily_limit 等），
     * 故必须插入一行真值（事务内，tearDown 回滚）。
     */
    private function enableWithdrawSwitch(): void
    {
        if (Db::table('platform_config')->where('group', 'withdraw')->where('key', 'global_switch')->exists()) {
            Db::table('platform_config')->where('group', 'withdraw')->where('key', 'global_switch')
                ->update(['value' => '1', 'type' => 'bool']);

            return;
        }

        Db::table('platform_config')->insert([
            'id'    => SnowflakeService::generate(),
            'group' => 'withdraw',
            'key'   => 'global_switch',
            'value' => '1',
            'type'  => 'bool',
        ]);
    }

    /**
     * 走控制器真身 apply()：全局开关 → 验证码 → Redis 串行锁 → applyLocked。
     * 请求必须带客户端身份（captchaRequest），否则整套件共用 0.0.0.0 那个 30 次/60 秒的桶。
     *
     * @return array<string, mixed> 解出的响应信封
     */
    private function apply(): array
    {
        $params = $this->captchaParams();
        $body = (string) json_encode([
            'platform_amount' => self::REQUEST_AMOUNT,
            'method'          => 'paypal',
            'account_info'    => 'probe@example.com',
            'captcha_key'     => $params['captcha_key'],
            'clicks'          => $params['clicks'],
        ]);

        $request = $this->captchaRequest(
            "POST /api/v1/withdraw/apply HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n{$body}"
        );
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $this->userId;

        return json_decode((string) (new WithdrawController())->apply($request)->rawBody(), true) ?? [];
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
