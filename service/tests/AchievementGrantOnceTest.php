<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\service\AchievementService;
use app\service\WalletScope;
use app\service\WalletService;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 成就只发一次经验（AchievementService::evaluate 的 check-then-act 修法）。
 *
 * 危害：旧写法是「读 completed=0 → 判 → 存 → VipService::addExp」。两个并发事件各自读到
 * completed=0、各自 addExp ⇒ **该 (user, achievement) 发两次经验**。
 * 这不只是积分：addExp 会顺带升 VIP 等级，而等级直接决定 getExchangeDiscount() 的兑换折扣（钱）。
 *
 * 发经验的唯一凭据改成「条件 UPDATE 认领首次完成」的 affected rows 之后，钉两件事：
 *  - 同一成就重复投递事件只发一次（正控：旧实现也过，防「整个发奖逻辑被关掉」也绿）
 *  - 库上必须真的有 uk(user_id, achievement_id)：这是「重复行」这个形状在盘上不可达的原因，
 *    也是本文件另一条断言的护栏（见下）
 *
 * ⚠ 红证边界（诚实交代）：**这次的修法没有可序列化复现的红点。**
 *   - 「两个进程同时读到 completed=0、各自 addExp」需要真并发（起跑线必须设在事务内首次一致性读之前），
 *     单进程用例证不了；
 *   - 「重复行各自成为兑奖券」在序列化执行下**旧实现也过**：第一次事件读到的行被置 1，第二次事件
 *     读到的还是同一行（first() 无 ORDER BY 但 InnoDB 按主键扫），早退 ⇒ 只发一次；
 *   - 而重复行本身在这套 schema 上根本插不进去（实测 1062 `game_user_achievement.uk_user_achievement`）。
 * ⇒ 本文件给的是**正控 + schema 契约**，不是红证；真并发红点归 lead 已认领的并发沙盘。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 ExchangeWalletIntegrationTest 的口径）。
 */
class AchievementGrantOnceTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $achievementId = 0;

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
        $this->achievementId = $this->makeAchievement();

        // 进度来源就是本用户的流水条数（metricCount → game_transaction），造一条即达标
        $this->assertTrue(
            WalletService::mutate($this->userId, WalletScope::platform(), '+100', 'deposit', 'test', 0),
            '备款应成功'
        );
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        foreach (['exp_log', 'user_achievement', 'transaction', 'user_wallet', 'user_game_wallet'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        Db::table('user_vip')->where('user_id', $this->userId)->delete();
        if ($this->achievementId > 0) {
            Db::table('achievement')->where('id', $this->achievementId)->delete();
        }
    }

    /** 正控：同一成就重复投递事件，只发一次经验 */
    #[Test]
    public function repeatedEventsGrantExpExactlyOnce(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AchievementService::handle('user.login', ['user_id' => $this->userId]);
        }

        $this->assertSame(1, $this->expGrantCount(), '同一成就重复投递事件只能发一次经验');
        $this->assertSame(10, $this->totalExp(), 'VIP 经验应恰好等于该成就 points（10）');
        $this->assertSame(1, (int) Db::table('user_achievement')->where('user_id', $this->userId)
            ->where('achievement_id', $this->achievementId)->value('completed'));
        // 认领后进度固定为目标值，不会随指标回落（连续签到断签）被改小
        $this->assertSame(1, (int) Db::table('user_achievement')->where('user_id', $this->userId)
            ->where('achievement_id', $this->achievementId)->value('progress'));
    }

    /**
     * schema 契约：uk(user_id, achievement_id) 必须在。
     *
     * 它是「同一成就的重复行」这个形状在盘上不可达的唯一原因。evaluate() 的插入竞争处理
     * （1062 时先确认行确实存在，否则上抛——防止把 id 撞号当成"别人插过了"，
     * 进而对一行不存在的记录做 CAS、静默不发经验）**是照着 uk 存在写的**。
     * 若哪天有迁移把这个键删掉：并发插入会留下重复行，此时唯一的防线退化成 CAS 的
     * `WHERE completed = 0`（第一次 UPDATE 命中多行=认领成功，第二个进程命中 0 行=不发）——
     * 逻辑仍不会双发经验，但会留下需要人工收敛的重复行。**删键之前先读 evaluate() 的 docblock。**
     */
    #[Test]
    public function userAchievementUniqueKeyExistsOnConfiguredSchema(): void
    {
        $indexes = Db::select(
            'SELECT INDEX_NAME AS name, COLUMN_NAME AS col FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0'
            . ' ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            ['game_user_achievement']
        );

        $byName = [];
        foreach ($indexes as $row) {
            $byName[$row->name][] = $row->col;
        }

        $this->assertArrayHasKey(
            'uk_user_achievement',
            $byName,
            '库上缺 uk(user_id, achievement_id)：重复行会变成可达形状，见本用例 docblock 与 evaluate() 注释'
        );
        $this->assertSame(['user_id', 'achievement_id'], $byName['uk_user_achievement']);
    }

    private function makeAchievement(): int
    {
        $id = SnowflakeService::generate();
        Db::table('achievement')->insert([
            'id'             => $id,
            'key'            => 'grant-once-test-' . $id,
            'name'           => 'grant once test',
            'description'    => '',
            'icon'           => '',
            'points'         => 10,
            'condition_json' => json_encode([
                'event'     => 'user.login',
                'metric'    => 'count',
                'table'     => 'transaction',
                'column'    => 'user_id',
                'threshold' => 1,
            ]),
        ]);

        return $id;
    }

    private function expGrantCount(): int
    {
        return Db::table('exp_log')->where('user_id', $this->userId)
            ->where('source', 'achievement')->where('ref_id', $this->achievementId)->count();
    }

    private function totalExp(): int
    {
        return (int) Db::table('user_vip')->where('user_id', $this->userId)->value('total_exp');
    }

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
