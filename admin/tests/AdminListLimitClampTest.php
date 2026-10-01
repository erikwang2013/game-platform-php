<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 列表端点的每页条数一律夹到 **[1, 200]**。
 *
 * 缺陷形状：17 处读分页参数的地方都是裸 `(int) $request->input('limit', 15)`，**没有上界**。
 * 三层兜底都实测过、全不存在：中间件不夹、BaseController 不夹、illuminate 的 `Builder::limit()`
 * 只把负数夹成 0、**不设上界**。于是 `?limit=10000000` 能落到任何一张表上，
 * 最要紧的四个是大表：`PlatformUserController::list`→game_user、
 * `LogController::index`→game_operation_log、`WithdrawController::orders`→game_withdraw_order、
 * `IdentityController::list`→game_user_identity。
 *
 * 上界取 **200**（不是 risk 族的 100）：200 是本仓客户端**已经写死的最大合法取数** ——
 * `game/list` 在 Flutter（game_server_page.dart:29、leaderboard_page.dart:32）、
 * React（community.tsx:45、TabPage.tsx:301）、Angular（community.spec.ts:81）三棵树里
 * 都是 `limit/pageSize = 200` 一次性拉全游戏下拉；`/admin/v1/role` 在 React
 * （adminUsers.ts:103）同样取 200。夹到 100 会让这些下拉**静默少一半选项**
 * （运营以为"本来就没这个游戏/角色"）—— 那是比"参数无上界"更坏的回归。夹到 200
 * 对既有合法调用分毫不差，同时把 `?limit=10000000` 这类请求从"拉全表"压成最多 200 行。
 * 范本照抄 risk 族的 `min(100, max(1, (int) $request->get('size', 20)))`（RiskUserController.php:48），
 * 只换上界常量；14 个 `limit` + 2 个 `per_page` + Activity 那处内联，**同一个参数同一个上界**。
 *
 * ⚠ 为什么钉 SQL 而不是钉响应行数：响应行数是**结果**，它同时受 where 条件、库内数据量影响，
 * 库恰好没那么多行时"没夹住"也会表现成正常。生成的 SQL 直接暴露 LIMIT 字面量，不受数据影响。
 * 每档都加一条 `assertCount(1, …)`，防止捕获到别的查询造成假绿
 * （见 ReviewQueueOrderingTest 踩过的 `getQueryLog()` 只增不减那坑）。
 *
 * ⚠ 下界这条钉的**不是** "limit 0"（旧写法在负数上更糟）：
 *   - `limit=-5` 进 illuminate `Builder::limit()`（vendor/illuminate/database/Query/Builder.php:2748）
 *     走的是 `if ($value >= 0)` —— **负数被整条丢弃、limit 留 null**，而调用方照样挂了
 *     `->offset(...)` ⇒ 生成 `… order by … offset 0`（**没有 LIMIT**）。这不是"空列表"：
 *     `offset` 不带 `limit` 是非法 MySQL ⇒ SQLSTATE 42000 1064 ⇒ **整个端点 500**。
 *     （实测：`select * from game_withdraw_order order by created_at desc, id desc offset 0`）
 *     任务书写的"只把负数夹成 0"是反的，实测是"负数 = SQL 语法错 / 500"。
 *     → 所以本档在变异读数里是 **Errors: 1** 而不是 Failures，断言在 `limitOf()` 里先炸。
 *   - `limit=0` 才是真的 `limit 0`（页面内容凭空消失而 total 仍非零）；
 *     `per_page=0` 例外：走 `paginate()` 的 `$perPage = value($perPage, $total) ?: getPerPage()`
 *     （Eloquent/Builder.php:1031）—— 0 是 falsy ⇒ 回落成模型默认每页数。
 *
 * ⚠ 还有一处 `paginate()` 的短路，害本用例的第一版在 `transactions` 上捕不到任何 SQL：
 * `Eloquent/Builder.php:1033` 是 `$results = $total ? $this->forPage(...)->get() : $this->model->newCollection();`
 * —— **count 为 0 时 SELECT 根本不生成**，也就没有 LIMIT 可钉。所以该端点必须先播一行
 * （见 {@see probeTransactionUser()}），否则"没夹住"和"夹住了"都表现为"捕获不到 SQL"。
 *
 * 变异读数：把任一处改回 `(int) $request->input(...)` ⇒ 该端点的 oversize 档红（limit 1000000）；
 * 把 `max(1, …)` 去掉 ⇒ 该端点的 zero/negative 档红。
 */
final class AdminListLimitClampTest extends TestCase
{
    /** 上界常量：改这个数必须同步改所有端点的 clamp，本条用例会立刻红。 */
    private const MAX_LIMIT = 200;

    /** 为 paginate() 的 count>0 门槛播的探针用户；0 = 尚未播。 */
    private int $probeUserId = 0;

    /** @var int[] tearDown 要删的 game_transaction.id */
    private array $transactionIds = [];

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
    }

    protected function tearDown(): void
    {
        Db::table('transaction')->whereIn('id', $this->transactionIds)->delete();
        $this->transactionIds = [];
        $this->probeUserId    = 0;
    }

    /**
     * `PlatformUserController::transactions` 用 `paginate()`，而 Eloquent/Builder.php:1033 在
     * **count 为 0 时压根不生成 SELECT**（`$total ? forPage(...)->get() : newCollection()`）——
     * 于是 LIMIT 无从钉起、"没夹住"与"夹住了"都表现成"捕获不到 SQL"。
     * 播一行流水把 count 抬到 1，本端点的三档才真正有判别力。
     *
     * 用雪花 ID 当探针 user_id（不碰 user_id=1 可能存在的真实数据），
     * `Transaction::where('user_id', …)` 不与 game_user 关联，故无需播用户行。
     */
    private function probeTransactionUser(): int
    {
        if ($this->probeUserId === 0) {
            $this->probeUserId = SnowflakeService::generate();
            $id                = SnowflakeService::generate();
            Db::table('transaction')->insert([
                'id'            => $id,
                'user_id'       => $this->probeUserId,
                'type'          => 'deposit',
                'amount'        => '1.00000000',
                'balance_after' => '1.00000000',
            ]);
            $this->transactionIds[] = $id;
        }

        return $this->probeUserId;
    }

    // ============================================================
    // 覆盖清单：控制器 / 方法 / 分页参数名 / 请求路径
    // ============================================================

    /**
     * 17 处 = 15 个 limit 家族（含 ActivityController 那处内联）+ 2 个 per_page。
     *
     * @return array<string, array{0:string,1:string,2:string,3:string}> [控制器, 方法, 参数名, 路径(可含 {hashid})]
     */
    public static function listEndpoints(): array
    {
        return [
            // ---- 大表四颗（最要紧）----
            'PlatformUserController::list (game_user)'            => [\app\admin\v1\controller\PlatformUserController::class, 'list', 'limit', '/admin/v1/platform/user/list'],
            'LogController::index (game_operation_log)'           => [\app\admin\v1\controller\LogController::class, 'index', 'limit', '/admin/v1/log/list'],
            'WithdrawController::orders (game_withdraw_order)'    => [\app\admin\v1\controller\WithdrawController::class, 'orders', 'limit', '/admin/v1/withdraw/orders'],
            'IdentityController::list (game_user_identity)'       => [\app\admin\v1\controller\IdentityController::class, 'list', 'limit', '/admin/v1/identity/list'],

            // ---- 其余 ----
            'UserController::index'            => [\app\admin\v1\controller\UserController::class, 'index', 'limit', '/admin/v1/user'],
            'GameController::list'             => [\app\admin\v1\controller\GameController::class, 'list', 'limit', '/admin/v1/game/list'],
            'CouponController::list'           => [\app\admin\v1\controller\CouponController::class, 'list', 'limit', '/admin/v1/coupon/list'],
            'GroupController::list'            => [\app\admin\v1\controller\GroupController::class, 'list', 'limit', '/admin/v1/group/list'],
            'LeaderboardController::list'      => [\app\admin\v1\controller\LeaderboardController::class, 'list', 'limit', '/admin/v1/leaderboard/list'],
            'AnnouncementController::list'     => [\app\admin\v1\controller\AnnouncementController::class, 'list', 'limit', '/admin/v1/announcement/list'],
            'CountryConfigController::list'    => [\app\admin\v1\controller\CountryConfigController::class, 'list', 'limit', '/admin/v1/country/config/list'],
            'RoleController::index'            => [\app\admin\v1\controller\RoleController::class, 'index', 'limit', '/admin/v1/role'],
            'TicketController::list'           => [\app\admin\v1\controller\TicketController::class, 'list', 'limit', '/admin/v1/ticket/list'],
            'ActivityController::list'         => [\app\admin\v1\controller\ActivityController::class, 'list', 'limit', '/admin/v1/activity/list'],
            'ConfigController::index'          => [\app\admin\v1\controller\ConfigController::class, 'index', 'limit', '/admin/v1/config'],

            // ---- per_page 两颗 ----
            'PlatformUserController::transactions (per_page)' => [\app\admin\v1\controller\PlatformUserController::class, 'transactions', 'per_page', '/admin/v1/platform/user/{hashid}/transactions'],
            'SearchController::search (per_page)'             => [\app\admin\v1\controller\SearchController::class, 'search', 'per_page', '/admin/v1/search?q=probe&type=game'],
        ];
    }

    // ============================================================
    // 调用与 SQL 捕获
    // ============================================================

    /** 走一次端点，返回**本次**调用里带 limit 的那条 SQL。 */
    private function limitSql(string $class, string $method, string $uri): string
    {
        $connection = Db::connection();
        // flushQueryLog 是必须的：disableQueryLog() 不清缓冲，enableQueryLog() 也不清，
        // 同进程里上一次调用的查询会被一起带回来（本用例一个方法内只捕获一次，但用例串跑时不然）
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $request = new Request("GET {$uri} HTTP/1.1\r\nHost: localhost\r\n\r\n");
            $args    = [$request];
            if (str_contains($uri, '{hashid}')) {
                $args[] = HashidsService::encode($this->probeTransactionUser());
            }
            (new $class())->{$method}(...$args);
            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $selects = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'limit') !== false
        ));

        $this->assertCount(
            1,
            $selects,
            "{$class}::{$method} 带 limit 的查询应恰好一条（count 查询不带 limit），实际：" . json_encode($selects)
        );

        return $selects[0];
    }

    /** 从 SQL 里取 LIMIT 的字面量。 */
    private function limitOf(string $sql): int
    {
        $this->assertSame(
            1,
            preg_match('/\blimit\s+(\d+)/i', $sql, $m),
            '这条 SQL 里没有可解析的 limit 子句：' . $sql
        );

        return (int) $m[1];
    }

    // ============================================================
    // 一、超大取数被夹到上界
    // ============================================================

    #[Test]
    #[DataProvider('listEndpoints')]
    public function oversizedLimitIsClampedToTheCeiling(string $class, string $method, string $param, string $uri): void
    {
        $uri .= (str_contains($uri, '?') ? '&' : '?') . "{$param}=1000000&page=1";
        $sql  = $this->limitSql($class, $method, $uri);

        $this->assertSame(
            self::MAX_LIMIT,
            $this->limitOf($sql),
            "{$param}=1000000 没被夹到上界 —— 无上界时这条 SQL 会一次拉走整张表。实际 SQL：" . $sql
        );
    }

    // ============================================================
    // 二、下界：0 与负数一律抬到 1
    //（旧写法在负数上会退化成**没有 LIMIT 子句**，见类注释；非 paginate 端点的 0 才是 limit 0）
    // ============================================================

    #[Test]
    #[DataProvider('listEndpoints')]
    public function zeroLimitIsRaisedToAtLeastOne(string $class, string $method, string $param, string $uri): void
    {
        $uri .= (str_contains($uri, '?') ? '&' : '?') . "{$param}=0&page=1";
        $this->assertSame(
            1,
            $this->limitOf($this->limitSql($class, $method, $uri)),
            "{$param}=0 必须抬到 1：非 paginate 端点旧写法直接生成 `limit 0`（页面内容凭空消失而 total 仍非零），"
            . 'paginate 端点则被 `?: getPerPage()` 悄悄换成模型默认每页数'
        );
    }

    #[Test]
    #[DataProvider('listEndpoints')]
    public function negativeLimitIsRaisedToAtLeastOne(string $class, string $method, string $param, string $uri): void
    {
        $uri .= (str_contains($uri, '?') ? '&' : '?') . "{$param}=-5&page=1";
        $this->assertSame(
            1,
            $this->limitOf($this->limitSql($class, $method, $uri)),
            "{$param}=-5 必须抬到 1。旧写法最坏的一档：illuminate 的 limit() 是 `if (\$value >= 0)`，"
            . '负数被**整条丢弃**、limit 留 null，而端点照挂 offset ⇒ SQL 成了 `… offset 0`（无 LIMIT），'
            . 'MySQL 判语法错 1064 ⇒ 该端点直接 500（此处会先撞上面“没有可解析的 limit 子句”那条断言）'
        );
    }

    // ============================================================
    // 三、缺省值没被夹取改掉（回归护栏）
    // ============================================================

    /**
     * 不传分页参数时，各端点必须回落到它原本的缺省（limit 家族 15、Ticket 20、per_page 20）——
     * 夹取只该管上下界，不该顺手把缺省值也改掉。
     *
     * @return array<string, array{0:string,1:string,2:string,3:string,4:int}>
     */
    public static function defaultLimits(): array
    {
        $cases = [];
        foreach (self::listEndpoints() as $name => [$class, $method, $param, $uri]) {
            $default = match (true) {
                str_contains($name, 'TicketController') => 20,
                $param === 'per_page'                   => 20,
                default                                 => 15,
            };
            $cases[$name] = [$class, $method, $param, $uri, $default];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('defaultLimits')]
    public function defaultPageSizeIsUnchanged(string $class, string $method, string $param, string $uri, int $default): void
    {
        $uri .= (str_contains($uri, '?') ? '&' : '?') . 'page=1';

        $this->assertSame(
            $default,
            $this->limitOf($this->limitSql($class, $method, $uri)),
            "{$class}::{$method} 的缺省每页条数被改了 —— 前端按自己的 pageSize 算页数，"
            . '两边对不上会尾页取不到'
        );
    }
}
