<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\IdentityController;
use app\admin\v1\controller\WithdrawController;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 两条**审核队列**的翻页次序必须是全序：`order by created_at desc, id desc`。
 *
 *  - GET /admin/v1/withdraw/orders（提现审核队列，WithdrawController::orders）
 *  - GET /admin/v1/identity/list（KYC 审核队列，IdentityController::list）
 *
 * 两处原先都只有 `->orderBy('created_at','desc')`，而两表的 `created_at` 都是**秒精度 DATETIME**
 * （game_withdraw_order: install.sql:282；game_user_identity: install.sql:505）——
 * 同秒多笔（批量提现、活动结束集中申请）时，同秒行之间的先后由 MySQL 自行决定，
 * 而 LIMIT/OFFSET 是逐页独立执行的 ⇒ **翻页会重复或漏行，且 `total`/`last_page` 依然正确**
 * （账面自洽、行对不上）。审核队列漏行 = 有人的提现/KYC 永远排不到，没人会收到报错。
 *
 * ⚠ 为什么本文件**同时**有一条行为用例和一条 SQL 用例，以及行为用例抓不到什么：
 * 缺第二排序键时同秒行的先后是 MySQL 的**未定义行为**，本机实测它恰好按主键稳定返回 ——
 * 于是「三页拼起来不重不漏」那条在**删掉 id 次序后依然全绿**（假钉子）。想让行为层红就得赌
 * 优化器当次选哪条访问路径，那是把钉子建在沙子上。所以次序本身由
 * {@see bothQueuesOrderByCreatedAtThenIdInSql} 直接钉生成的 SQL：它不赌运气、能红。
 * 行为用例保留，用来钉**分页算术**（total/last_page/尾页条数）这些 SQL 之外的回归。
 *
 * 变异读数：删掉任一处的 `->orderBy('id','desc')` ⇒ SQL 用例红（且只红那一条路由的断言）。
 */
final class ReviewQueueOrderingTest extends TestCase
{
    /**
     * 行为用例的隔离手段：两个端点**都只能按 status 过滤**（没有 user_id 参数），
     * 而库里本来就可能有别人播的 pending 行 —— 直接断言 `total === 5` 会跟着库内容飘。
     * 用一个随机 status 值当探针标记，两个端点都照常 `where('status', $status)`，
     * 于是 total 精确等于本用例播下的条数。列宽 VARCHAR(20)，13 字符够用。
     */
    private string $withdrawStatus = '';
    private string $identityStatus = '';

    /** @var int[] tearDown 要删的 game_user.id */
    private array $userIds = [];

    /** @var int[] tearDown 要删的 game_withdraw_order.id */
    private array $orderIds = [];

    /** @var int[] tearDown 要删的 game_user_identity.id */
    private array $identityIds = [];

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->withdrawStatus = 'wprobe' . bin2hex(random_bytes(3));
        $this->identityStatus = 'iprobe' . bin2hex(random_bytes(3));
    }

    protected function tearDown(): void
    {
        Db::table('withdraw_order')->whereIn('id', $this->orderIds)->delete();
        Db::table('user_identity')->whereIn('id', $this->identityIds)->delete();
        Db::table('user')->whereIn('id', $this->userIds)->delete();
        $this->orderIds = $this->identityIds = $this->userIds = [];
    }

    // ============================================================
    // 播种
    // ============================================================

    private function seedUser(): int
    {
        $id = SnowflakeService::generate();
        Db::table('user')->insert([
            'id'       => $id,
            'username' => 'order_probe_' . $id,
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'status'   => 1,
        ]);
        $this->userIds[] = $id;

        return $id;
    }

    /** 直插一条提现订单；createdAt 相同即制造"同秒多笔"。status 用本用例的探针标记。 */
    private function seedOrder(int $userId, string $createdAt): int
    {
        $id = SnowflakeService::generate();
        Db::table('withdraw_order')->insert([
            'id'              => $id,
            'order_no'        => 'probe' . $id,
            'user_id'         => $userId,
            'platform_amount' => '10.0000',
            'status'          => $this->withdrawStatus,
            'created_at'      => $createdAt,
        ]);
        $this->orderIds[] = $id;

        return $id;
    }

    /** 直插一条 KYC 记录（uk_user_id 唯一 ⇒ 一个用户一条）。status 用本用例的探针标记。 */
    private function seedIdentity(int $userId, string $createdAt): int
    {
        $id = SnowflakeService::generate();
        Db::table('user_identity')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'real_name'  => 'probe',
            'id_number'  => 'probe',
            'status'     => $this->identityStatus,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $this->identityIds[] = $id;

        return $id;
    }

    // ============================================================
    // 调用
    // ============================================================

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function withdrawPage(string $query = ''): array
    {
        $request  = new Request("GET /admin/v1/withdraw/orders{$query} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $response = (new WithdrawController())->orders($request);
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function identityPage(string $query = ''): array
    {
        $request  = new Request("GET /admin/v1/identity/list{$query} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $response = (new IdentityController())->list($request);
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    /**
     * 跑一次调用，回收**本次**生成的、带 limit 的 SELECT。
     *
     * 两处都要 `with('user')` ⇒ 会有第二条预加载查询（`where id in (…)`，无 limit），
     * 按 `limit` 过滤后主查询是唯一一条。
     *
     * ⚠ `flushQueryLog()` 是**必须**的，不是保险：`disableQueryLog()` 只是关掉开关，
     * **不清空缓冲**，而 `enableQueryLog()` 也不清 —— 于是第二次捕获会把上一次的查询一起带回来。
     * 本文件捕获两次（提现 + KYC），少了这一句时 `$selects[0]` 恒为**提现**那条：
     * 提现的变异照样能红（它恰好在缓冲首位），KYC 的变异则被前面那条正确 SQL 挡成**假绿**
     * （实测：删掉 IdentityController 的 id 次序后本用例仍全绿）。
     *
     * @return string[] 带 limit 的 SQL
     */
    private function captureSql(callable $call): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $call();
            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        return array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'limit') !== false
        ));
    }

    /** 全序的判据：created_at desc 之后必须紧跟 id desc，二者缺一不可。 */
    private function assertTotalOrder(string $sql, string $what): void
    {
        $this->assertMatchesRegularExpression(
            '/order by\s+`?created_at`?\s+desc\s*,\s*`?id`?\s+desc/i',
            $sql,
            "{$what}缺 id 第二排序键：created_at 是秒精度 DATETIME，同秒多笔时翻页次序由 MySQL 自行"
            . '决定（未定义行为），会出现同一行在两页里各出现一次、另一行谁都看不到，'
            . "而 total/last_page 仍然正确。实际 SQL：" . $sql
        );
    }

    // ============================================================
    // 一、SQL 全序（本文件的判别力所在）
    // ============================================================

    #[Test]
    public function bothQueuesOrderByCreatedAtThenIdInSql(): void
    {
        // 播一条：证明这两条查询真的在跑，而不是被某个提前 return 跳过了（那样 SQL 断言会落空）
        $userId = $this->seedUser();
        $this->seedOrder($userId, '2026-01-02 03:04:05');
        $this->seedIdentity($this->seedUser(), '2026-01-02 03:04:05');

        // 两条各自只该抓到一条带 limit 的查询，且必须真的是**各自那张表** ——
        // 少了这层表名钉子，捕获错线路（把提现的 SQL 当成 KYC 的）会表现成假绿。
        $withdrawSql = $this->captureSql(fn () => $this->withdrawPage('?page=1&limit=2'));
        $this->assertCount(1, $withdrawSql, '提现队列带 limit 的查询应恰好一条，实际：' . json_encode($withdrawSql));
        $this->assertStringContainsString('game_withdraw_order', $withdrawSql[0], '抓到的不是提现队列那条 SQL');
        $this->assertTotalOrder($withdrawSql[0], '提现审核队列 ');

        $identitySql = $this->captureSql(fn () => $this->identityPage('?page=1&limit=2'));
        $this->assertCount(1, $identitySql, 'KYC 队列带 limit 的查询应恰好一条，实际：' . json_encode($identitySql));
        $this->assertStringContainsString('game_user_identity', $identitySql[0], '抓到的不是 KYC 队列那条 SQL');
        $this->assertTotalOrder($identitySql[0], 'KYC 审核队列 ');
    }

    // ============================================================
    // 二、翻页算术（行为层；见类注释：它抓不到缺第二排序键）
    // ============================================================

    #[Test]
    public function withdrawQueuePagesAddUpToTheFullSet(): void
    {
        $userId = $this->seedUser();
        // 5 条**同一秒**的订单：正是会踩到"同秒行序未定义"的形状
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->seedOrder($userId, '2026-01-02 03:04:05');
        }

        $filter = 'status=' . $this->withdrawStatus;

        $first = $this->withdrawPage("?{$filter}&page=1&limit=2");
        $this->assertSame(5, $first['total'], 'total 应与 limit 无关（探针 status 过滤后应恰好 5 条）');
        $this->assertSame(2, $first['limit'], 'limit 必须回显请求值');
        $this->assertCount(2, $first['list']);

        $last = $this->withdrawPage("?{$filter}&page=3&limit=2");
        $this->assertCount(1, $last['list'], '尾页应剩 1 条');

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            foreach ($this->withdrawPage("?{$filter}&page={$page}&limit=2")['list'] as $order) {
                $seen[] = HashidsService::decode((string) $order['id']);
            }
        }
        sort($seen);
        $expected = $ids;
        sort($expected);
        $this->assertSame($expected, $seen, '三页拼起来必须不重不漏');
    }

    #[Test]
    public function identityQueuePagesAddUpToTheFullSet(): void
    {
        // uk_user_id 唯一：每个用户一条 KYC
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->seedIdentity($this->seedUser(), '2026-01-02 03:04:05');
        }

        $filter = 'status=' . $this->identityStatus;

        $first = $this->identityPage("?{$filter}&page=1&limit=2");
        $this->assertSame(5, $first['total'], 'total 应与 limit 无关（探针 status 过滤后应恰好 5 条）');
        $this->assertCount(2, $first['list']);

        $last = $this->identityPage("?{$filter}&page=3&limit=2");
        $this->assertCount(1, $last['list'], '尾页应剩 1 条');

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            foreach ($this->identityPage("?{$filter}&page={$page}&limit=2")['list'] as $identity) {
                $seen[] = HashidsService::decode((string) $identity['id']);
            }
        }
        sort($seen);
        $expected = $ids;
        sort($expected);
        $this->assertSame($expected, $seen, '三页拼起来必须不重不漏');
    }
}
