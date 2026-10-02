<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\process\GroupSweepWorker;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * GroupSweepWorker 的 member_count 校正 —— 它存在的理由就是「正常路径没走到」的那些组。
 *
 * 缺陷：原查询 `INNER JOIN group_member m ... WHERE m.left_at IS NULL` 会把**成员全退光的组**
 * 整行吃掉（它们在成员表里没有任何活跃行）⇒ 校正循环永远碰不到它，member_count 停在崩溃前的
 * 非零值。正常路径 GroupController::leave()/解散 会自己置 0，正因如此这个兜底才只剩「异常路径」
 * 这一种输入 —— 而 INNER JOIN 恰好把这类输入全部滤掉了，兜底退化成空转。
 *
 * ⚠ 只把 INNER JOIN 换成 LEFT JOIN 并**不够**：`->whereNull('m.left_at')` 写在 WHERE 里时，
 * LEFT JOIN 给无匹配行补出来的 NULL 行会被 WHERE 一起滤掉，效果与 INNER JOIN 等同。
 * 判据必须落在 ON 里（本用例的 allMembersLeft... 就是钉这一条的：它只对「成员行存在但全退光」
 * 的组为红，对「压根没有成员行」的组两种写法都为绿）。
 *
 * 库名必须含 test；全部写入包在外层事务里，tearDown 整笔回滚。
 */
final class GroupSweepCountCorrectionTest extends TestCase
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

    /**
     * 真钉子：成员行**存在但全部已退出**的组必须被校正到 0。
     * 这条对「LEFT JOIN + WHERE left_at IS NULL」的假修法为红（该写法下这组整行消失）。
     */
    #[Test]
    public function allMembersLeftStillGetsCorrectedToZero(): void
    {
        $groupId = $this->seedGroup(memberCount: 3);
        // 3 条成员行，全部已退群（软删）—— 组在成员表里没有任何活跃行
        foreach ([1, 2, 3] as $i) {
            $this->seedMember($groupId, 990001000 + $i, leftAt: date('Y-m-d H:i:s', strtotime('-' . $i . ' hours')));
        }

        $this->assertSame(0, $this->activeMembers($groupId), '前提：成员行存在但一条活跃的都没有');

        (new GroupSweepWorker())->sweep();

        $this->assertSame(0, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            '成员全退光的组必须被校正到 0（INNER JOIN 会把这组整行吃掉、校正循环永远碰不到它）');
    }

    /** 补一条更极端的：压根没有成员行的组（INNER JOIN 同样吃掉，LEFT JOIN 两种写法都能过） */
    #[Test]
    public function zeroMemberGroupIsAlsoCorrected(): void
    {
        $groupId = $this->seedGroup(memberCount: 5);

        (new GroupSweepWorker())->sweep();

        $this->assertSame(0, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            '一条成员行都没有的组同样要归零');
    }

    /** 正例：有活跃成员时按活跃行数校正，已退出的行不计数 */
    #[Test]
    public function countFollowsActiveMembersOnly(): void
    {
        $groupId = $this->seedGroup(memberCount: 7);
        $this->seedMember($groupId, 990002001);                                  // 活跃
        $this->seedMember($groupId, 990002002);                                  // 活跃
        $this->seedMember($groupId, 990002003, date('Y-m-d H:i:s', strtotime('-1 day'))); // 已退出

        (new GroupSweepWorker())->sweep();

        $this->assertSame(2, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            'member_count 必须等于**活跃**成员行数（已退出的行不得计数）');
    }

    /** 负例闸：本来就正确的组不得被改写（别把校正写成无条件写 0） */
    #[Test]
    public function alreadyCorrectGroupIsLeftAlone(): void
    {
        $groupId = $this->seedGroup(memberCount: 2);
        $this->seedMember($groupId, 990003001);
        $this->seedMember($groupId, 990003002);

        (new GroupSweepWorker())->sweep();

        $this->assertSame(2, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            '计数本来就对的组不得被改写');
    }

    // ============================================================
    // helpers
    // ============================================================

    private function seedGroup(int $memberCount): int
    {
        $id = (int) SnowflakeService::generate();
        // type=guild + expire_at=NULL：绕开 sweep() 的 team 到期解散分支，只测计数校正
        Db::table('group')->insert([
            'id'           => $id,
            'type'         => 'guild',
            'name'         => 'sweep probe ' . $id,
            'owner_id'     => 990000001,
            'member_count' => $memberCount,
            'status'       => 1,
        ]);

        return $id;
    }

    private function seedMember(int $groupId, int $userId, ?string $leftAt = null): void
    {
        Db::table('group_member')->insert([
            'id'       => (int) SnowflakeService::generate(),
            'group_id' => $groupId,
            'user_id'  => $userId,
            'role'     => 'member',
            'left_at'  => $leftAt,
        ]);
    }

    private function activeMembers(int $groupId): int
    {
        return (int) Db::table('group_member')->where('group_id', $groupId)->whereNull('left_at')->count();
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
