<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\PlatformUserController;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Webman\Route;

/**
 * GET /admin/v1/platform/user/{hashid}/transactions —— 运营查某平台用户的流水（只读）。
 *
 * 形状是**冻结契约**：与 C 端 /api/v1/wallet/transactions 逐字段同形（两棵树前端对照渲染），
 * 所以这里钉的不是「有个接口」，而是四条具体形状 + 一条隔离：
 *  1) `id` / `ref_id` 必须是 hashid 且**能 decode 回原值** —— 裸 BIGINT 会 decode 成别的数或抛异常；
 *     `ref_id=0` 走 null 分支（C 端同款，不是 hashid(0)）。
 *  2) 只吐该用户的流水（两个用户各播两条，互不串）。
 *  3) 分页四键 total/page/per_page/last_page 与 per_page 自洽，且**翻页不重不漏**
 *     （created_at 是秒精度 DATETIME，同秒多笔是常态 ⇒ 只有 created_at 排序会重复/漏行）。
 *  4) amount/balance_after 是字符串、created_at 原样吐（不转 ISO）。
 *
 * 直接 new 控制器调用（不经过 HTTP 层）：鉴权链由路由契约用例 + AdminPermissionMatchingTest 覆盖。
 * 只打测试库：库名不含 test 直接 fail（沿用 WithdrawRefundLedgerTest 口径）。
 */
final class PlatformUserTransactionsTest extends TestCase
{
    private int $userId = 0;
    private int $otherUserId = 0;

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

        $this->userId      = SnowflakeService::generate();
        $this->otherUserId = SnowflakeService::generate();

        foreach ([$this->userId, $this->otherUserId] as $id) {
            Db::table('user')->insert([
                'id'       => $id,
                'username' => 'tx_probe_' . $id,
                'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
                'status'   => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $ids = array_values(array_filter([$this->userId, $this->otherUserId]));
        $this->userId = $this->otherUserId = 0;
        if ($ids === []) {
            return;
        }

        // 本用例只经 Db::table 直插流水（不走钱包写路径 ⇒ 不产生 wallet.mutated 事件行）。
        // 仍按本用户流水派生的 event_id 精确删一次：将来若有人把播种改成走 WalletService，
        // 这里不会留下 outbox 残留，也不会踩到并发写这张表的别人。
        $txIds = Db::table('transaction')->whereIn('user_id', $ids)->pluck('id')->all();
        if ($txIds !== []) {
            Db::table('event_outbox')->whereIn('event_id', array_map(
                static fn ($id) => 'wallet.mutated:' . $id,
                $txIds
            ))->delete();
        }

        Db::table('transaction')->whereIn('user_id', $ids)->delete();
        Db::table('user')->whereIn('id', $ids)->delete();
    }

    // ============================================================
    // 播种与取值
    // ============================================================

    /** 直插一条流水（game_transaction 的真表名是 Db::table('transaction')，前缀由 Db 补）。 */
    private function seed(
        int $userId,
        string $type,
        string $amount,
        ?int $refId = null,
        string $createdAt = '2026-01-02 03:04:05'
    ): int {
        $id = SnowflakeService::generate();
        Db::table('transaction')->insert([
            'id'            => $id,
            'user_id'       => $userId,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $amount,
            'ref_type'      => $refId === null ? '' : 'withdraw',
            'ref_id'        => $refId ?? 0,
            'remark'        => 'probe',
            'created_at'    => $createdAt,
        ]);

        return $id;
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function page(string $query = '', ?int $userId = null): array
    {
        $hashid   = HashidsService::encode($userId ?? $this->userId);
        $request  = new Request(
            "GET /admin/v1/platform/user/{$hashid}/transactions{$query} HTTP/1.1\r\nHost: localhost\r\n\r\n"
        );
        $response = (new PlatformUserController())->transactions($request, $hashid);
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    /** hashid 钉子：既要是字符串，又要能 decode 回原值（裸 BIGINT 会 decode 成别的数或直接抛异常）。 */
    private function assertHashid(int $expected, mixed $actual, string $what): void
    {
        $this->assertIsString($actual, "{$what} 不是字符串（裸 BIGINT 的典型形状）：" . var_export($actual, true));
        $this->assertSame($expected, HashidsService::decode($actual), "{$what} 解不回原值 —— 前端拿它去查必然对不上");
    }

    // ============================================================
    // 一、形状：hashid 双向 + ref_id=0 的 null 分支
    // ============================================================

    #[Test]
    public function idsAndRefIdsAreHashidsNotRawBigints(): void
    {
        $refId = SnowflakeService::generate();
        $txId  = $this->seed($this->userId, 'withdraw', '-10.00000000', $refId);

        $data = $this->page();
        $this->assertCount(1, $data['items'], '应当只有一条流水');
        $item = $data['items'][0];

        $this->assertHashid($txId, $item['id'], 'items[0].id');
        $this->assertHashid($refId, $item['ref_id'], 'items[0].ref_id');
        $this->assertSame('withdraw', $item['ref_type']);
    }

    #[Test]
    public function refIdZeroComesBackAsNullNotAsEncodedZero(): void
    {
        // game_transaction.ref_id 是 NOT NULL DEFAULT 0（DDL: install.sql 的 game_transaction 建表段）：无关联单据的流水
        // 存的是 0。契约要的是 null（C 端同款），吐 hashid(0) 会让前端渲染出一个不存在的单据号。
        $this->seed($this->userId, 'deposit', '5.00000000');

        $item = $this->page()['items'][0];
        $this->assertNull($item['ref_id'], 'ref_id=0 必须吐 null');
    }

    #[Test]
    public function amountsAreStringsAndCreatedAtIsVerbatim(): void
    {
        $this->seed($this->userId, 'deposit', '100.00000000', null, '2026-01-02 03:04:05');

        $item = $this->page()['items'][0];

        // DECIMAL(20,8) 走字符串（金额一律不经 float，见项目 CLAUDE.md）
        $this->assertIsString($item['amount'], 'amount 必须是字符串，不是 JSON number');
        $this->assertSame('100.00000000', $item['amount']);
        $this->assertIsString($item['balance_after']);
        $this->assertSame('100.00000000', $item['balance_after']);
        // 原样吐：转成 ISO（2026-01-02T03:04:05.000000Z）会与 C 端那份形状分叉
        $this->assertSame('2026-01-02 03:04:05', $item['created_at'], 'created_at 必须原样吐，不许转格式');
    }

    // ============================================================
    // 二、隔离：只吐该用户的流水
    // ============================================================

    #[Test]
    public function onlyTheRequestedUsersRowsComeBack(): void
    {
        $mineA = $this->seed($this->userId, 'deposit', '1.00000000');
        $mineB = $this->seed($this->userId, 'game_spend', '-2.00000000');
        $this->seed($this->otherUserId, 'deposit', '3.00000000');
        $this->seed($this->otherUserId, 'deposit', '4.00000000');

        $data = $this->page();

        $this->assertSame(2, $data['total'], 'total 把别人的流水也算进来了');
        $this->assertCount(2, $data['items']);
        $returned = array_map(static fn ($item) => HashidsService::decode($item['id']), $data['items']);
        sort($returned);
        $expected = [$mineA, $mineB];
        sort($expected);
        $this->assertSame($expected, $returned, '返回的流水不是本用户那两条');

        // 反向正控：另一个用户自己那条请求也只看得到自己的（别是"永远只返回某个固定用户"）
        $this->assertSame(2, $this->page('', $this->otherUserId)['total']);
    }

    #[Test]
    public function unknownUserYieldsEmptyPageInsteadOfLeaking(): void
    {
        $this->seed($this->userId, 'deposit', '1.00000000');

        $data = $this->page('', SnowflakeService::generate());

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['items']);
    }

    // ============================================================
    // 三、过滤与分页
    // ============================================================

    #[Test]
    public function typeFilterNarrowsTheLedger(): void
    {
        $this->seed($this->userId, 'deposit', '1.00000000');
        $this->seed($this->userId, 'deposit', '2.00000000');
        $this->seed($this->userId, 'withdraw', '-3.00000000');

        $this->assertSame(3, $this->page()['total'], '不带 type 时应当全量 3 条');

        $data = $this->page('?type=deposit');
        $this->assertSame(2, $data['total'], 'type 过滤没生效');
        $this->assertSame(['deposit'], array_values(array_unique(array_column($data['items'], 'type'))));
    }

    #[Test]
    public function paginationKeysAreConsistentAndPagesDoNotRepeatOrSkip(): void
    {
        // 5 条**同一秒**的流水（created_at 是秒精度 DATETIME，一局游戏成对写 earn/spend 就会同秒）。
        // ⚠ 本条的「不重不漏」是**端到端性质检查**（能抓分页算错），但**抓不到「少了 id 第二排序键」**：
        //   实测删掉 id 次序后它照样全绿 —— 同秒行的先后是 MySQL 未定义行为，本机恰好按主键稳定返回。
        //   全序由 ledgerOrderingIsTotalSoPagingCannotRepeatRows 直接钉 SQL。
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->seed($this->userId, 'deposit', '1.0000000' . $i, null, '2026-01-02 03:04:05');
        }

        $first = $this->page('?page=1&per_page=2');
        $this->assertSame(5, $first['total'], 'total 应与 per_page 无关');
        $this->assertSame(1, $first['page']);
        $this->assertSame(2, $first['per_page'], 'per_page 必须回显请求值');
        $this->assertSame(3, $first['last_page'], 'last_page = ceil(5/2)');
        $this->assertCount(2, $first['items']);

        $last = $this->page('?page=3&per_page=2');
        $this->assertSame(3, $last['page']);
        $this->assertCount(1, $last['items'], '尾页应剩 1 条');

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            foreach ($this->page("?page={$page}&per_page=2")['items'] as $item) {
                $seen[] = HashidsService::decode($item['id']);
            }
        }
        sort($seen);
        $expected = $ids;
        sort($expected);
        $this->assertSame($expected, $seen, '三页拼起来必须不重不漏（少了/多了都是排队次序不稳定）');
    }

    /**
     * 翻页次序必须是**全序**：`order by created_at desc, id desc`。
     *
     * 为什么这条不看行为看 SQL：只按 created_at（秒精度）排时，同秒行的先后是 MySQL 的
     * **未定义行为** —— 本机实测它恰好按主键稳定返回，所以「三页拼起来不重不漏」那条用例
     * 在**删掉 id 次序后依然全绿**（假钉子）。想让行为层红就得赌优化器当次选哪个访问路径，
     * 那是把钉子建在沙子上。退而求其次：直接钉生成的 SQL 里有第二排序键，这条能红且不赌运气。
     */
    #[Test]
    public function ledgerOrderingIsTotalSoPagingCannotRepeatRows(): void
    {
        $this->seed($this->userId, 'deposit', '1.00000000', null, '2026-01-02 03:04:05');

        Db::connection()->enableQueryLog();
        try {
            $this->page('?page=1&per_page=2');
            $log = Db::connection()->getQueryLog();
        } finally {
            Db::connection()->disableQueryLog();
        }

        $selects = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'limit') !== false
        ));
        $this->assertNotSame([], $selects, '没抓到带 limit 的查询，SQL 断言无从谈起（查询日志没生效？）');

        $this->assertMatchesRegularExpression(
            '/order by\s+`?created_at`?\s+desc\s*,\s*`?id`?\s+desc/i',
            $selects[0],
            '流水查询缺 id 第二排序键：created_at 是秒精度 DATETIME，同秒多笔时翻页次序由 MySQL 自行决定'
            . '（未定义行为），会出现同一行在两页里各出现一次、另一行谁都看不到。实际 SQL：' . $selects[0]
        );
    }

    // ============================================================
    // 四、路由契约：四棵树前端打的就是这条 GET
    // ============================================================

    /**
     * 路由 dump 必须独占进程：Route::load() 每次都清空 dispatcher 静态状态，
     * 同进程第二次调用拿回 0 条路由（PermissionSeedParityTest 同一处置）。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function transactionsRouteIsWiredInsideTheAdminAuthGroup(): void
    {
        Route::load([__DIR__ . '/../config']);

        $info = Route::dispatch('GET', '/admin/v1/platform/user/AbCd1234/transactions');
        $this->assertSame(\FastRoute\Dispatcher::FOUND, $info[0], 'GET /admin/v1/platform/user/{hashid}/transactions 未命中路由');
        $this->assertSame([PlatformUserController::class, 'transactions'], $info[1]['callback']);

        $middlewares = $info[1]['route']->getMiddleware();
        foreach ([
            \app\middleware\AdminAuth::class,
            \app\middleware\AdminPermission::class,
            \app\middleware\OperationLog::class,
        ] as $middleware) {
            $this->assertContains($middleware, $middlewares, "流水端点缺 {$middleware}（必须继承 /admin/v1 组的三层）");
        }

        // 新路由不得顶掉同一前缀的详情路由（{hashid} 段数更少的那条仍在）
        $this->assertSame(
            \FastRoute\Dispatcher::FOUND,
            Route::dispatch('GET', '/admin/v1/platform/user/AbCd1234')[0],
            '详情路由被新路由顶掉了'
        );

        // 公开组不得有它：这是管理端的用户资金数据
        $this->assertSame(
            \FastRoute\Dispatcher::NOT_FOUND,
            Route::dispatch('GET', '/api/v1/platform/user/AbCd1234/transactions')[0],
            '流水端点不得出现在无鉴权的 /api/v1 公开组'
        );
    }
}
