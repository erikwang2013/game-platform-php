<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\service\WalletScope;
use app\service\WalletService;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * WalletService 的冻结语义（lock/unlock）与累计列闸（$trackStats 真生效）。
 *
 * 钉两件事：
 * 1) lock 是桶间转移，不是吞掉余额：available 减、frozen 等额增；unlock 反向还原。
 *    修复前 lock 传 $fromFrozen=false ⇒ frozen 恒不动，被「冻结」的可用余额既不在 available
 *    也不在 frozen，从用户视角凭空消失；且 unlock 会把 frozen 扣成负数被透支闸挡下 ⇒ 不可逆。
 * 2) 累计列只认真实收支：lock/unlock 不动 total_earned/total_spent。
 *    mutateStillTracksCumulativeStats 是这条的反例——防止有人把累计逻辑整个关掉也让前两个用例绿。
 *
 * 只打测试库：库名不含 test 直接 fail，绝不静默写开发库（沿用 ExchangeWalletIntegrationTest 的口径）。
 */
class WalletServiceLockTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;

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

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId = SnowflakeService::generate();
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 钱包写路径自 2026-09-28 起落 wallet.mutated 事件行（Outbox）：按本用户流水派生的 event_id
        // 精确删除，不按全表计数/全表删（并发跑测试时别人也在写这张表）
        $txIds = Db::table('transaction')->where('user_id', $this->userId)->pluck('id')->all();
        if ($txIds !== []) {
            Db::table('event_outbox')->whereIn('event_id', array_map(
                static fn ($id) => 'wallet.mutated:' . $id,
                $txIds
            ))->delete();
        }

        // 只删本用例造的行（snowflake ID 唯一），不 TRUNCATE、不碰他人数据
        foreach (['transaction', 'wallet_hold', 'user_wallet'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
    }

    /** 冻结必须把可用余额搬进冻结列，而不是把它抹掉 */
    #[Test]
    public function lockFreezesAvailableInsteadOfConsumingIt(): void
    {
        $scope = WalletScope::platform();
        $this->assertTrue(WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0), '备款应成功');

        $this->assertTrue(WalletService::lock($this->userId, $scope, '30', 'test_hold', 1), '冻结应成功');

        $this->assertSame(0, bccomp($this->wallet('balance'), '70', 8), '可用余额减 30：' . $this->wallet('balance'));
        $this->assertSame(
            0,
            bccomp($this->wallet('frozen_balance'), '30', 8),
            '冻结列必须等额增 30（修复前恒为 0，钱被吞掉）：' . $this->wallet('frozen_balance')
        );
        $this->assertSame(
            0,
            bccomp(bcadd($this->wallet('balance'), $this->wallet('frozen_balance'), 8), '100', 8),
            '桶间转移：可用 + 冻结必须恒等于冻结前总额'
        );
    }

    /** 冻结不是收支：累计列一概不动（$trackStats=false 的那一半） */
    #[Test]
    public function lockAndUnlockLeaveCumulativeStatsUntouched(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        WalletService::lock($this->userId, $scope, '30', 'test_hold', 1);

        $this->assertSame(0, bccomp($this->wallet('total_earned'), '100', 8), '冻结不是收入：total_earned 应仍为 100');
        $this->assertSame(
            0,
            bccomp($this->wallet('total_spent'), '0', 8),
            '冻结不是支出：total_spent 应仍为 0（修复前被记成 30）'
        );

        // refType/refId 指回上面那笔冻结（'test_hold', 1）：台账里同 ref 的活跃 hold 会被优先消费，
        // 实际消费了哪几笔见 unlock 流水的 remark 与 game_wallet_hold（不再靠「最近一笔冻结」推断）
        $this->assertTrue(WalletService::unlock($this->userId, $scope, '30', 'test_hold', 1), '解冻应成功');

        $this->assertSame(0, bccomp($this->wallet('balance'), '100', 8), '解冻后可用余额还原');
        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '0', 8), '解冻后冻结清零');
        $this->assertSame(
            0,
            bccomp($this->wallet('total_earned'), '100', 8),
            '一次「冻结→解冻」不得虚增收入（修复前 +30 记成 130）'
        );
        $this->assertSame(0, bccomp($this->wallet('total_spent'), '0', 8), '解冻不是支出：total_spent 仍为 0');
    }

    /** 反例：真实出入账仍必须累计，防止「整个累计逻辑被关掉」也能让上面两个用例绿 */
    #[Test]
    public function mutateStillTracksCumulativeStats(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::mutate($this->userId, $scope, '-40', 'withdraw', 'test', 0);

        $this->assertSame(0, bccomp($this->wallet('total_earned'), '100', 8), '真实入账应计入 total_earned');
        $this->assertSame(0, bccomp($this->wallet('total_spent'), '40', 8), '真实出账应计入 total_spent');
        $this->assertSame(0, bccomp($this->wallet('balance'), '60', 8), '可用余额 = 100 − 40');
    }

    /** 可用余额不足：返回 false，且余额/冻结/流水三处都不留痕 */
    #[Test]
    public function lockBeyondAvailableBalanceWritesNothing(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        // 备款那笔自己就落一条流水，所以这里比的是「失败前后条数不变」
        $before = $this->transactionCount();

        $this->assertFalse(
            WalletService::lock($this->userId, $scope, '100.00000001', 'test_hold', 1),
            '超过可用余额的冻结应返回 false'
        );

        $this->assertSame(0, bccomp($this->wallet('balance'), '100', 8), '失败后可用余额原样');
        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '0', 8), '失败后冻结列原样');
        $this->assertSame($before, $this->transactionCount(), '失败路径不得落流水');
    }

    /** 解冻超过冻结额：透支闸挡下（frozen 列是 UNSIGNED，DB 侧也不允许负数） */
    #[Test]
    public function unlockBeyondFrozenBalanceWritesNothing(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'test_hold', 1);

        $this->assertFalse(WalletService::unlock($this->userId, $scope, '30.00000001', 'test_hold', 1), '超过冻结额应返回 false');
        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '30', 8), '失败后冻结列原样');
        $this->assertSame(0, bccomp($this->wallet('balance'), '70', 8), '失败后可用余额原样');
        $this->assertSame(0, bccomp($this->wallet('total_earned'), '100', 8), '失败后累计列原样');
    }

    private function wallet(string $column): string
    {
        return (string) Db::table('user_wallet')->where('user_id', $this->userId)->value($column);
    }

    private function transactionCount(): int
    {
        return (int) Db::table('transaction')->where('user_id', $this->userId)->count();
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
