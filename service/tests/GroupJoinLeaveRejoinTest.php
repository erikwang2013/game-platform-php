<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\GroupController;
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
 * GroupController 组队/公会成员关系 —— 退群后再加入（M5 确定性功能缺陷）。
 *
 * 缺陷：`uk_group_user(group_id,user_id)` 是**硬唯一**（install/install.sql:1750），而退群只软删
 * （leave() 置 left_at、member_count 减 1）。join() 原先撞 1062 一律回 422「Already a member」
 * ⇒ 用户退群后**永远**回不去，而成员读取处处 `whereNull('left_at')`（人确实已经不在组里）。
 * 修法是撞键时**复活同键行**（清 left_at、重置角色与加入时间），不是硬删除（那会丢历史）。
 *
 * 顺带钉住 create() 的 expire_at 格式校验：到期判定读 `strtotime(expire_at)`，
 * 非日期串会让它得 false ⇒ 过期组被当成永不过期。
 *
 * 库名必须含 test；全部写入包在外层事务里，tearDown 整笔回滚。
 */
final class GroupJoinLeaveRejoinTest extends TestCase
{
    private static bool $booted = false;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 响应要过 encodeId()/decodeId()，依赖 hashids 容器绑定（webman 插件 bootstrap 注册的，
        // PHPUnit 下要手动起；同 WalletTransactionsOrderingTest）
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

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    // ============================================================
    // 一、真钉子：join → leave → join 三步，且第三次之后确实在组里
    // ============================================================

    #[Test]
    public function leavingThenJoiningAgainPutsTheSameRowBackInTheGroup(): void
    {
        $owner  = (int) SnowflakeService::generate();
        $member = (int) SnowflakeService::generate();

        $created = $this->create($owner, ['type' => 'guild', 'name' => 'Rejoin probe']);
        $this->assertSame(0, $created['code'] ?? -1,
            '前提：建组应成功。实际：' . json_encode($created, JSON_UNESCAPED_UNICODE));
        $hashid  = $created['data']['id'];
        $groupId = (int) HashidsService::decode($hashid);

        // 1) 加入
        $joined = $this->call('join', $hashid, [], $member);
        $this->assertSame(0, $joined['code'] ?? -1,
            '前提：首次加入应成功。实际：' . json_encode($joined, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->activeCount($groupId, $member), '前提：加入后应恰好一条活跃成员行');
        $rowIdBefore = (int) Db::table('group_member')
            ->where('group_id', $groupId)->where('user_id', $member)->value('id');

        // 2) 退出（软删）
        $left = $this->call('leave', $hashid, [], $member);
        $this->assertSame(0, $left['code'] ?? -1,
            '前提：退出应成功。实际：' . json_encode($left, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $this->activeCount($groupId, $member), '前提：退出后应查不到活跃成员（leave 是软删）');
        $this->assertSame(1, (int) Db::table('group_member')
            ->where('group_id', $groupId)->where('user_id', $member)->count(),
            '前提：退出只是软删 —— 行还在，这正是重加入会撞唯一键的原因');

        // 3) 再加入 —— 缺陷点：硬唯一键 + 软删 ⇒ 旧代码在这里回 422「Already a member」
        $rejoined = $this->call('join', $hashid, [], $member);
        $this->assertSame(0, $rejoined['code'] ?? -1,
            '退群后必须能重新加入（uk_group_user 是硬唯一，而退群只置 left_at）。'
            . '实际：' . json_encode($rejoined, JSON_UNESCAPED_UNICODE));

        // 不止断「接口返回成功」——要断**真的在组里**
        $this->assertSame(1, $this->activeCount($groupId, $member), '重加入后应有且仅有一条活跃成员行');
        $this->assertSame(1, (int) Db::table('group_member')
            ->where('group_id', $groupId)->where('user_id', $member)->count(),
            '必须是复活同键行，不能留下两行（硬删除再插入同样是错的）');
        $this->assertSame($rowIdBefore, (int) Db::table('group_member')
            ->where('group_id', $groupId)->where('user_id', $member)->value('id'),
            '必须复活原来那一行（硬删除会丢掉贡献值等历史）');
        $this->assertNull(Db::table('group_member')
            ->where('group_id', $groupId)->where('user_id', $member)->value('left_at'),
            '复活后 left_at 必须清空，否则成员读取（处处 whereNull(left_at)）仍看不见他');

        // 成员列表：本人恰好一次，且 member_count 冗余列跟着回来
        $list = $this->members($hashid, $owner);
        $mine = array_values(array_filter(
            $list['items'],
            static fn (array $i): bool => $i['user_id'] === HashidsService::encode($member)
        ));
        $this->assertCount(1, $mine,
            '成员列表里本人应恰好出现一次（0 次＝没真进组，2 次＝插重了）。实际列表：'
            . json_encode($list['items'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(2, $list['total'], 'owner + 本人 = 2 人（退群时减过的 member_count 要加回来）');
        $this->assertSame(2, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            'member_count 冗余列必须与成员列表一致（退群 -1、重加入 +1）');
    }

    #[Test]
    public function joiningWhileStillAnActiveMemberStillFailsWith422(): void
    {
        $owner  = (int) SnowflakeService::generate();
        $member = (int) SnowflakeService::generate();

        $created = $this->create($owner, ['type' => 'guild', 'name' => 'Duplicate probe']);
        $hashid  = $created['data']['id'];
        $groupId = (int) HashidsService::decode($hashid);

        $this->assertSame(0, $this->call('join', $hashid, [], $member)['code'] ?? -1, '前提：首次加入应成功');

        $again = $this->call('join', $hashid, [], $member);
        $this->assertSame(422, $again['code'] ?? -1,
            '仍在组的成员重复加入仍须 422（复活逻辑只对**已退群**的旧行生效，不能把重复加入也放行）。'
            . '实际：' . json_encode($again, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->activeCount($groupId, $member), '重复加入不得多出一条活跃成员行');
        $this->assertSame(2, (int) Db::table('group')->where('id', $groupId)->value('member_count'),
            '重复加入不得把 member_count 加两次');
    }

    // ============================================================
    // 二、顺带：expire_at 格式校验（非日期串 ⇒ 到期判定失效）
    // ============================================================

    #[Test]
    public function createRejectsANonDateExpireAtAndStillAcceptsAGenuineOne(): void
    {
        $gameId = (int) SnowflakeService::generate();
        Db::table('game')->insert([
            'id'     => $gameId,
            'name'   => 'Probe game',
            'slug'   => 'probe-' . $gameId,
            'status' => 1,
        ]);
        $gameHashid = HashidsService::encode($gameId);
        $owner      = (int) SnowflakeService::generate();

        $bad = $this->create($owner, [
            'type' => 'team', 'name' => 'Bad expire', 'game_id' => $gameHashid, 'expire_at' => 'not-a-date',
        ]);
        $this->assertSame(422, $bad['code'] ?? -1,
            '非日期串必须在落库前被挡下：它会让 join/解散判定里的 strtotime(expire_at) 得 false，'
            . '把过期组当成永不过期。实际：' . json_encode($bad, JSON_UNESCAPED_UNICODE));

        $good = $this->create($owner, [
            'type' => 'team', 'name' => 'Good expire', 'game_id' => $gameHashid,
            'expire_at' => '2099-12-31 23:59:59',
        ]);
        $this->assertSame(0, $good['code'] ?? -1,
            '合法日期串必须放行（校验别把正常值一起挡了）。实际：' . json_encode($good, JSON_UNESCAPED_UNICODE));

        $this->assertSame('2099-12-31 23:59:59',
            (string) Db::table('group')->where('id', (int) HashidsService::decode($good['data']['id']))->value('expire_at'),
            '合法值必须原样落库（到期判定读的就是它）');
    }

    // ============================================================
    // helpers
    // ============================================================

    /** 走控制器真身：$method 是 join/leave（都收 (Request, string $hashid)） */
    private function call(string $method, string $hashid, array $body, int $userId): array
    {
        $response = (new GroupController())->{$method}(
            $this->request('POST', "/api/v1/groups/{$hashid}/{$method}", $body, $userId),
            $hashid
        );

        return $this->envelope($response);
    }

    private function create(int $userId, array $body): array
    {
        return $this->envelope(
            (new GroupController())->create($this->request('POST', '/api/v1/groups', $body, $userId))
        );
    }

    private function members(string $hashid, int $userId): array
    {
        $response = (new GroupController())->members(
            $this->request('GET', "/api/v1/groups/{$hashid}/members", [], $userId),
            $hashid
        );
        $body = $this->envelope($response);
        $this->assertSame(0, $body['code'] ?? -1,
            '成员列表应成功。实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    /** 活跃（未退群）成员行数 */
    private function activeCount(int $groupId, int $userId): int
    {
        return (int) Db::table('group_member')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->count();
    }

    private function request(string $method, string $path, array $body, int $userId): Request
    {
        $headers = "Host: localhost\r\n";
        $payload = '';
        if ($body !== []) {
            $payload = (string) json_encode($body);
            $headers .= "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n";
        }

        $request = new Request("{$method} {$path} HTTP/1.1\r\n{$headers}\r\n{$payload}");
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId;

        return $request;
    }

    private function envelope(Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
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
