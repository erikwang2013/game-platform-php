<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\DeviceTokenController;
use common\model\DeviceToken;
use Illuminate\Database\Eloquent\Builder;
use Monolog\Handler\TestHandler;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use support\Db;
use support\Log;
use support\Request;

/**
 * 设备推送令牌改绑：语义、留痕、以及原先裸奔的 1062。
 *
 * 背景（三件已核实的事实，别推翻）：
 *  1. 端点只按 token 查、不限当前用户 ⇒ 谁的 token 被提交，行就改绑给谁；
 *  2. `game_device_token` 有全局唯一键 `idx_token`；
 *  3. service 全树**没有 logout 端点** ⇒ 若加 `where('user_id')` 归属条件，同设备换人登录后
 *     这个 token 会永久绑死旧账号、新账号再也注册不上（撞 1062 且旧代码未捕获 ⇒ 未捕获 500）。
 *
 * 故本批**维持改绑语义**（FCM/APNS 令牌属于设备不属于账号），只补两件事：
 *  - 归属真的变化时留一条可查记录（from/to）；
 *  - 预查与 INSERT 之间被并发插队时（撞 idx_token），按改绑收尾而不是把未捕获异常抛给客户端。
 *
 * 判据（每条都实测过，见类尾注释的变异读数）：
 *  - 顺序：A 注册 T → B 注册同一 T ⇒ 不抛异常 / 成功信封 / owner 是 B / 一条 from→to 留痕；
 *  - 并发：预查看不到已存在的行 ⇒ 真撞 1062 ⇒ 同上四条，不得 500；
 *  - 收窄：1062 但键名不是 idx_token（典型 snowflake 撞主键）⇒ **原样上抛**，不许吞成「已改绑」。
 *
 * 需要真库（唯一键冲突是库行为，mock 不出来）；MySQL 不可用则 skip。
 * 每例自建自清，并拒绝在库名不含 test 的库上写。
 */
final class DeviceTokenRebindTest extends TestCase
{
    private const USER_A = 990000601;
    private const USER_B = 990000602;
    private const TOKEN = 'test-rebind-token-990000601';

    private TestHandler $logs;

    /** @var array<string, mixed> 竞态用例会往模型上挂临时全局作用域，原样存这里、tearDown 还原 */
    private array $scopes = [];

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

        DeviceToken::where('token', self::TOKEN)->delete();
        $this->scopes = DeviceToken::getAllGlobalScopes();

        // 观察**生产用的那个** Log：Log::info() 静态转发到 channel('default') 的同一实例
        $this->logs = new TestHandler();
        Log::channel('default')->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        Log::channel('default')->popHandler();
        DeviceToken::setAllGlobalScopes($this->scopes);
        DeviceToken::where('token', self::TOKEN)->delete();

        parent::tearDown();
    }

    #[Test]
    public function secondUserTakesOverTheTokenAndLeavesARebindTrace(): void
    {
        $this->assertSame(0, $this->register(self::USER_A)['code'], '首次注册应成功');
        $this->assertSame(self::USER_A, $this->ownerOf(self::TOKEN));

        // 调用若抛异常，本用例直接 error —— 「不抛异常」本身就是断言
        $body = $this->register(self::USER_B);

        $this->assertSame(0, $body['code'], '改绑必须返回成功信封');
        $this->assertTrue($body['data']['registered'] ?? false);
        $this->assertSame(self::USER_B, $this->ownerOf(self::TOKEN), 'owner 必须换成后来的注册者');

        $trace = $this->rebindTrace();
        $this->assertCount(1, $trace, 'A→B 必须恰好留一条改绑记录');
        $this->assertSame(self::USER_A, $trace[0]['from_user_id'], '留痕必须带 from');
        $this->assertSame(self::USER_B, $trace[0]['to_user_id'], '留痕必须带 to');
    }

    #[Test]
    public function sameUserReregisterIsNotATrace(): void
    {
        $this->register(self::USER_A);
        $this->register(self::USER_A);

        $this->assertSame(self::USER_A, $this->ownerOf(self::TOKEN));
        $this->assertCount(0, $this->rebindTrace(),
            '同账号重复注册（每次冷启动都发生）不是改绑，记了只是噪音');
    }

    /**
     * 竞态路径：对手在「预查」与「INSERT」之间提交，预查落空 ⇒ 真的撞 idx_token。
     *
     * 单线程里造这条路的唯一办法就是让**第一次**预查看不见那行 —— 用一个只生效一次的
     * 全局作用域把它过滤掉（第二次查询起不再生效，故 catch 里的重查能拿到行）。
     * 这不是在测 mock：INSERT 撞的是**真库真唯一键**，抛的是真 QueryException。
     * 旧代码在这里就是未捕获 500（已实测）。
     */
    #[Test]
    public function concurrentInsertCollisionIsReboundInsteadOfThrown(): void
    {
        $this->seed(self::TOKEN, self::USER_A);

        $queries = 0;
        DeviceToken::addGlobalScope('race-once', function (Builder $builder) use (&$queries): void {
            if ($queries++ === 0) {
                $builder->whereRaw('1 = 0');
            }
        });

        $body = $this->register(self::USER_B);

        $this->assertSame(0, $body['code'], '并发撞 idx_token 不得变成未捕获 500');
        $this->assertTrue($body['data']['registered'] ?? false);
        $this->assertSame(self::USER_B, $this->ownerOf(self::TOKEN));

        $trace = $this->rebindTrace();
        $this->assertCount(1, $trace, '竞态改绑同样要留痕');
        $this->assertSame(self::USER_A, $trace[0]['from_user_id']);
        $this->assertSame(self::USER_B, $trace[0]['to_user_id']);
    }

    /**
     * 成对判据的「守禁」那一半：只按错误码 1062 一刀切，会把 snowflake 撞主键这类
     * 需要运维介入的系统性故障伪装成一次正常改绑（ReferralController / ActivityService 同一口径）。
     */
    #[Test]
    public function duplicateKeyNarrowingRefusesForeignKeys(): void
    {
        $predicate = self::predicate();

        $this->assertTrue($predicate(self::duplicateKey('game_device_token.idx_token'), 'idx_token'),
            '并发双写撞 idx_token 正是要认下的那一种');
        $this->assertTrue($predicate(self::duplicateKey('idx_token'), 'idx_token'),
            '键名不带表限定前缀也要认（MySQL 版本差异）');
        $this->assertFalse($predicate(self::duplicateKey('game_device_token.PRIMARY'), 'idx_token'),
            '本表主键（snowflake）撞号是故障，不是「已改绑」');
        // 已知取舍（与 DuplicateKeyNarrowingTest 同口径，如实钉住别让后人以为它是精确匹配）：
        // 判据是**子串**匹配，故形如 idx_token_* 的键名会被一并认下。本表上不存在这种键
        // （只有 idx_token / PRIMARY），不值得为它加一层解析。
        $this->assertTrue($predicate(self::duplicateKey('game_device_token.idx_token_backup'), 'idx_token'),
            '子串匹配的已知上限：形如 idx_token_* 的键名会被一并认下（当前表上不存在这种键）');
        $this->assertFalse($predicate(self::duplicateKey('game_device_token.idx_token', 1213), 'idx_token'),
            '只有 1062 才是重复键（1213 是死锁）');
        $this->assertFalse($predicate(new RuntimeException("Duplicate entry 'x' for key 'idx_token'"), 'idx_token'),
            '非 PDO 异常没有 errorInfo，消息里恰好出现键名也不行');
    }

    /** @return array<string, mixed> 响应信封 */
    private function register(int $userId): array
    {
        $request = new Request("POST /api/v1/device/token HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['platform' => 'fcm', 'token' => self::TOKEN]);
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId;

        return json_decode((new DeviceTokenController())->register($request)->rawBody(), true) ?: [];
    }

    private function seed(string $token, int $userId): void
    {
        $row = new DeviceToken();
        $row->id = 990000601001;
        $row->user_id = $userId;
        $row->platform = 'fcm';
        $row->token = $token;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save();
    }

    private function ownerOf(string $token): int
    {
        return (int) DeviceToken::where('token', $token)->value('user_id');
    }

    /**
     * 改绑留痕。断言的是日志**内容**（from/to），不是「有没有调 Log::info」——
     * 后者只要消息文案改一个字就假绿。
     *
     * @return list<array<string, mixed>>
     */
    private function rebindTrace(): array
    {
        return array_values(array_map(
            static fn (array $r): array => $r['context'],
            array_filter(
                $this->logs->getRecords(),
                static fn (array $r): bool => $r['message'] === 'Device token rebound'
            )
        ));
    }

    /** 取**生产用的那个**私有判据（不是测试内复制品） */
    private static function predicate(): callable
    {
        // 不调 setAccessible()：PHP 8.1 起它对反射调用不再有任何作用，8.5 起还会报弃用
        $method = new ReflectionMethod(DeviceTokenController::class, 'isDuplicateOnKey');

        return static fn (\Throwable $e, string $key): bool => (bool) $method->invoke(null, $e, $key);
    }

    /** 造一个 1062：$key 决定消息里出现的唯一键名（真实 PDO 消息会带 表.键 限定名） */
    private static function duplicateKey(string $key, int $driverCode = 1062): PDOException
    {
        $e = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 't' for key '{$key}'",
            0
        );
        $e->errorInfo = [23000, $driverCode, "Duplicate entry 't' for key '{$key}'"];

        return $e;
    }
}
