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
 * 冻结子台账 game_wallet_hold：frozen_balance 降级为聚合缓存，台账才是权威。
 *
 * 钉四件事：
 * 1) lock 一笔 = 落一行 hold，且不变量 Σ(remaining) == frozen_balance 恒成立；
 * 2) unlock 按笔消费：给了 ref 就吃那一笔（哪怕它更年轻），没给就 FIFO（最老优先）——
 *    修复前只能吃「最近一笔冻结」，这正是被推翻的旧模型；
 * 3) 凑不满 / 台账缺失时零写入失败（fail-closed），不落半笔；
 * 4) 不变量断言真的会咬人：绕过 WalletService 篡改台账 ⇒ 下一次资金操作抛异常并整笔回滚。
 *
 * 反例（positive control，证明上面不是空转）：unlockBeyondLedgerWritesNothing 失败路径必须零写入；
 * divergenceBetweenLedgerAndColumnRollsBackTheMutation 若不抛异常，说明断言是个摆设。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 WalletServiceLockTest 的口径）。
 */
class WalletFreezeLedgerTest extends TestCase
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

        // 事件行按本用户流水派生的 event_id 精确删除：绝不按全表计数/全表删，不碰别的用例的行
        $txIds = Db::table('transaction')->where('user_id', $this->userId)->pluck('id')->all();
        if ($txIds !== []) {
            Db::table('event_outbox')->whereIn(
                'event_id',
                array_map(static fn ($id) => 'wallet.mutated:' . $id, $txIds)
            )->delete();
        }

        foreach (['transaction', 'wallet_hold', 'user_wallet'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
    }

    /** 一笔冻结 = 一行 hold，remaining 初始等于 amount，不变量成立 */
    #[Test]
    public function lockWritesOneHoldPerFreezeAndKeepsTheInvariant(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        $this->assertTrue(WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1), '第一笔冻结应成功');
        $this->assertTrue(WalletService::lock($this->userId, $scope, '20', 'risk_hold', 2), '第二笔冻结应成功');

        $holds = $this->holds();
        $this->assertCount(2, $holds, '两笔冻结必须落两行台账（修复前一行都没有，释放只能靠「最近一笔」猜）');
        $this->assertSame('30.00000000', $holds[0]['remaining'], '第一行 = 第一笔的原始金额');
        $this->assertSame('20.00000000', $holds[1]['remaining'], '第二行 = 第二笔的原始金额');
        $this->assertSame('risk_hold', $holds[1]['ref_type'], '台账行必须记住冻结来源单据类型');
        $this->assertSame(2, (int) $holds[1]['ref_id'], '台账行必须记住来源单据 ID');
        $this->assertSame(WalletService::HOLD_ACTIVE, (int) $holds[1]['status'], '未释放 = 冻结中');
        $this->assertNull($holds[1]['released_at'], '未释放完不得盖释放时间戳');

        $this->assertLedgerMatchesColumn();
    }

    /** 给了 ref 就吃那一笔 —— 哪怕它是较新的那笔（FIFO 会给错人，正是旧模型的病） */
    #[Test]
    public function unlockConsumesTheTargetedHoldNotTheNewest(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);   // 较老
        WalletService::lock($this->userId, $scope, '20', 'risk_hold', 2);   // 较新

        $this->assertTrue(
            WalletService::unlock($this->userId, $scope, '15', 'risk_hold', 2),
            '指定 ref 的释放应成功'
        );

        $holds = $this->holds();
        $this->assertSame('30.00000000', $holds[0]['remaining'], '未被指定的较老冻结必须原封不动（FIFO 会吃掉它）');
        $this->assertSame('5.00000000', $holds[1]['remaining'], '被指定的较新冻结吃 15，剩 5');
        $this->assertSame(WalletService::HOLD_ACTIVE, (int) $holds[1]['status'], '还剩 5 ⇒ 仍是冻结中');
        $this->assertNull($holds[1]['released_at'], '未吃干净 ⇒ 不盖 released_at');

        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '35', 8), '冻结列 = 30 + 5');
        $this->assertSame(0, bccomp($this->wallet('balance'), '65', 8), '可用 = 100 − 30 − 20 + 15');

        // 流水自证：remark 写明这次实际吃的是哪一行（台账行 id），不再靠「最近一笔」推断
        $remark = (string) Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', 'unlock')->value('remark');
        $this->assertSame('解冻余额 hold:' . (int) $holds[1]['id'], $remark, '释放流水的 remark 必须点名被消费的 hold');

        $this->assertLedgerMatchesColumn();
    }

    /** 不给 ref（含 ''/0 这类不指向任何行的取值）⇒ FIFO：最老优先吃满 */
    #[Test]
    public function unlockWithoutTargetConsumesOldestFirst(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);
        WalletService::lock($this->userId, $scope, '20', 'risk_hold', 2);

        $this->assertTrue(WalletService::unlock($this->userId, $scope, '25', '', 0), '无 ref 释放应退化为 FIFO 并成功');

        $holds = $this->holds();
        $this->assertSame('5.00000000', $holds[0]['remaining'], '最老的先被吃：30 − 25 = 5');
        $this->assertSame('20.00000000', $holds[1]['remaining'], '较新的原封不动');

        $remark = (string) Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', 'unlock')->value('remark');
        $this->assertSame(
            '解冻余额 hold:' . (int) $holds[0]['id'],
            $remark,
            'FIFO 吃最老那一笔就凑满了（30 ≥ 25），remark 只该有它一行'
        );

        $this->assertLedgerMatchesColumn();
    }

    /** 跨多笔吃：吃干净的那笔才盖 released_at + status=released，部分释放的保持 NULL/冻结中 */
    #[Test]
    public function multiHoldReleaseStampsReleasedAtOnlyOnFullyConsumedHolds(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 2);

        $this->assertTrue(WalletService::unlock($this->userId, $scope, '45', 'risk_hold', 1), '跨两笔的释放应成功');

        $holds = $this->holds();
        $this->assertSame('0.00000000', $holds[0]['remaining'], '第一笔吃光');
        $this->assertSame(WalletService::HOLD_RELEASED, (int) $holds[0]['status'], '吃光 ⇒ status=已释放');
        $this->assertNotNull($holds[0]['released_at'], '吃光 ⇒ 盖释放时间戳');
        $this->assertSame('15.00000000', $holds[1]['remaining'], '第二笔吃 15，剩 15');
        $this->assertSame(WalletService::HOLD_ACTIVE, (int) $holds[1]['status'], '还剩 15 ⇒ 仍是冻结中');
        $this->assertNull($holds[1]['released_at'], '没吃光 ⇒ released_at 保持 NULL');

        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '15', 8), '冻结列 = 剩下的 15');
        $this->assertLedgerMatchesColumn();
    }

    /** 反例：台账凑不满 ⇒ 返回 false 且零写入（不落半笔、不动余额、不动台账） */
    #[Test]
    public function unlockBeyondLedgerWritesNothing(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);

        $before = $this->counts();

        $this->assertFalse(
            WalletService::unlock($this->userId, $scope, '30.00000001', 'risk_hold', 1),
            '超过台账口径的释放必须失败'
        );

        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '30', 8), '失败后冻结列原样');
        $this->assertSame(0, bccomp($this->wallet('balance'), '70', 8), '失败后可用余额原样');
        $this->assertSame('30.00000000', $this->holds()[0]['remaining'], '失败后台账原样');
        $this->assertSame($before, $this->counts(), '失败路径不得落流水/事件行');
    }

    /** 台账缺失（回填迁移没跑）而钱包有冻结：抛异常点名迁移文件，而不是笼统报「余额不足」 */
    #[Test]
    public function missingLedgerOnFrozenWalletFailsClosedNamingTheMigration(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        // 直接改列模拟旧库现状：frozen_balance 有值、台账一行都没有（锁/unlock 都改不到这一列）
        Db::table('user_wallet')->where('user_id', $this->userId)
            ->update(['frozen_balance' => '50.00000000']);
        $before = $this->counts();

        try {
            WalletService::unlock($this->userId, $scope, '10', 'risk_hold', 1);
            $this->fail('台账缺失却有冻结时，释放必须抛异常（fail-closed），不得静默当作「余额不足」');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString(
                '2026_09_28_wallet_freeze_ledger.sql',
                $e->getMessage(),
                '异常信息必须告诉运维该跑哪支迁移：' . $e->getMessage()
            );
        }

        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '50', 8), '抛异常后冻结列原样（事务回滚）');
        $this->assertSame($before, $this->counts(), '抛异常路径不得落流水/事件行');
    }

    /** 不变量断言真会咬人：绕过 WalletService 篡改台账 ⇒ 下一次资金操作抛异常并整笔回滚 */
    #[Test]
    public function divergenceBetweenLedgerAndColumnRollsBackTheMutation(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);
        $holdId = (int) $this->holds()[0]['id'];

        // 模拟「有别的写路径绕过 WalletService 动了冻结」：台账被改小，聚合列还是 30
        Db::table('wallet_hold')->where('id', $holdId)->update(['remaining' => '10.00000000']);

        try {
            WalletService::unlock($this->userId, $scope, '5', 'risk_hold', 1);
            $this->fail('台账与聚合列分叉时必须抛异常（否则 assertFreezeLedger 就是个摆设）');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('冻结台账分叉', $e->getMessage(), '异常应点名分叉：' . $e->getMessage());
        }

        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '30', 8), '整笔回滚：冻结列仍是 30');
        $this->assertSame(0, bccomp($this->wallet('balance'), '70', 8), '整笔回滚：可用余额仍是 70');
        $this->assertSame('10.00000000', $this->holds()[0]['remaining'], '回滚后台账保持被篡改的原样（不是被写成 5）');
    }

    /** 每笔钱包变动落且只落一行 outbox：event_id 由本笔流水雪花主键派生（稳定且唯一） */
    #[Test]
    public function walletMutationWritesExactlyOneOutboxRowKeyedByTransactionId(): void
    {
        $scope = WalletScope::platform();
        $this->assertTrue(WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0));

        $txId = (string) Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', 'deposit')->value('id');
        $eventId = 'wallet.mutated:' . $txId;

        $rows = Db::table('event_outbox')->where('event_id', $eventId)->get(['event', 'payload', 'status']);
        $this->assertCount(1, $rows, '一笔变动恰一行事件；且 event_id 必须就是「wallet.mutated:<流水雪花ID>」');
        $this->assertSame('wallet.mutated', (string) $rows[0]->event, '事件名必须是 RELIABLE_EVENTS 里的 wallet.mutated');

        $payload = json_decode((string) $rows[0]->payload, true);
        $this->assertSame($this->userId, (int) $payload['user_id'], 'payload 必须带 user_id');
        $this->assertSame('+100', (string) $payload['amount'], 'payload.amount 必须与本笔 delta 一致');
        $this->assertSame('100.00000000', (string) $payload['balance_after'], 'payload 必须带变动后余额');

        // 第二笔变动必须换一个 event_id（不稳定就重放成重复事件，正是可靠投递要治的病）
        WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);
        $lockTxId = (string) Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', 'lock')->value('id');
        $this->assertNotSame($eventId, 'wallet.mutated:' . $lockTxId, '不同变动的 eventId 必须不同');
        $this->assertSame(
            1,
            (int) Db::table('event_outbox')->where('event_id', 'wallet.mutated:' . $lockTxId)->count(),
            '第二笔变动同样恰一行'
        );
    }

    /** 反例：没有资金变动就没有事件行（失败路径不得留下「钱没动、事件发了」） */
    #[Test]
    public function failedMutationWritesNoOutboxRow(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        $this->assertSame(1, $this->outboxRowsForUser(), '备款那笔自己一行');

        $this->assertFalse(WalletService::lock($this->userId, $scope, '100.00000001', 'risk_hold', 1), '超可用余额的冻结应失败');

        $this->assertSame(1, $this->outboxRowsForUser(), '失败路径不得新增事件行');
    }

    /** 事件行与资金行同生共死：外层事务回滚 ⇒ 余额、流水、台账、事件行一起消失 */
    #[Test]
    public function outboxRowRollsBackWithTheMoney(): void
    {
        $scope = WalletScope::platform();
        WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0);

        $eventId = '';
        $rolledBack = false;
        try {
            Db::transaction(function () use ($scope, &$eventId) {
                WalletService::lock($this->userId, $scope, '30', 'risk_hold', 1);
                $txId = (string) Db::table('transaction')->where('user_id', $this->userId)
                    ->where('type', 'lock')->value('id');
                $eventId = 'wallet.mutated:' . $txId;
                if ((int) Db::table('event_outbox')->where('event_id', $eventId)->count() !== 1) {
                    throw new \RuntimeException('事件行没有并进当前事务');
                }
                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $e) {
            $rolledBack = $e->getMessage() === 'force rollback';
        }

        $this->assertTrue($rolledBack, '外层事务必须按预期回滚（若事件行没并进当前事务，上面那条断言会先炸）');
        $this->assertSame(0, bccomp($this->wallet('frozen_balance'), '0', 8), '回滚后冻结列归零');
        $this->assertSame(0, bccomp($this->wallet('balance'), '100', 8), '回滚后可用余额仍是 100');
        $this->assertSame(0, (int) Db::table('event_outbox')->where('event_id', $eventId)->count(), '回滚后事件行不得残留');
        $this->assertSame(0, (int) Db::table('wallet_hold')->where('user_id', $this->userId)->count(), '回滚后台账行不得残留');
    }

    /** @return array<int,array<string,mixed>> */
    private function holds(): array
    {
        return Db::table('wallet_hold')->where('user_id', $this->userId)->orderBy('id')->get()->map(
            static fn ($row) => (array) $row
        )->all();
    }

    /** 不变量：Σ(remaining) 必须等于聚合列 frozen_balance（bcadd 逐行加，不用 SQL SUM） */
    private function assertLedgerMatchesColumn(): void
    {
        $sum = '0';
        foreach ($this->holds() as $hold) {
            $sum = bcadd($sum, bcadd((string) $hold['remaining'], '0', 8), 8);
        }

        $this->assertSame(
            0,
            bccomp($this->wallet('frozen_balance'), $sum, 8),
            "不变量破了：frozen_balance={$this->wallet('frozen_balance')} vs Σremaining={$sum}"
        );
    }

    /** @return array{tx:int,outbox:int,hold:int} */
    private function counts(): array
    {
        return [
            'tx'     => (int) Db::table('transaction')->where('user_id', $this->userId)->count(),
            'outbox' => $this->outboxRowsForUser(),
            'hold'   => (int) Db::table('wallet_hold')->where('user_id', $this->userId)->count(),
        ];
    }

    /**
     * 本用户「流水 → 事件」的配对数：从资金侧的流水行反查 event_id（不解析 payload —— JSON 列
     * 会被 MySQL 规范化成 `"k": v`，按文本 LIKE 匹配是在测 MySQL 的空格口径，不是在测本服务）。
     * 钱与事件必须一一对应：多一行说明发了没钱的信，少一行说明钱动了没发事件。
     */
    private function outboxRowsForUser(): int
    {
        $ids = Db::table('transaction')->where('user_id', $this->userId)->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        return (int) Db::table('event_outbox')->whereIn(
            'event_id',
            array_map(static fn ($id) => 'wallet.mutated:' . $id, $ids)
        )->count();
    }

    private function wallet(string $column): string
    {
        return (string) Db::table('user_wallet')->where('user_id', $this->userId)->value($column);
    }

    /**
     * 让 support\Db 指向测试库（口径与 WalletServiceLockTest 相同：先烧掉 Initializer 的一次性初始化）。
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
