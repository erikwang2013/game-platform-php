<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ChatController;
use app\api\v1\controller\GameController;
use app\api\v1\controller\GroupController;
use app\api\v1\controller\NotificationController;
use app\api\v1\controller\TicketController;
use app\api\v1\controller\TournamentController;
use app\api\v1\controller\WalletController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 七个列表端点的 per_page 双端钳制（`max(1, min(100, (int) $request->input('per_page', N)))`）。
 *
 * 为什么**两个半边都承重**（口径与 SearchController:28-31 的注释同一件事）：
 *  - `min(100, …)` 挡 `?per_page=1000000`：一次把整表（+ 关联）拉进内存；
 *  - `max(1, …)` 挡 `?per_page=-1`：`Builder::limit()` 对负值是**忽略**（`if ($value >= 0)`，
 *    不抛错也不落 0），而 `offset` 照发 ⇒ 生成的查询只剩 `... offset 0`（**没有 limit 子句**）
 *    ⇒ MySQL 报 1064 ⇒ QueryException 冒到框架 ⇒ **稳定 500**。
 *    ⚠ 其中 `game/list` 在**公开组**（config/route.php，无 UserAuth）⇒ 匿名 `?per_page=-1` 即可打红。
 *
 * ⚠ 只在**真库 + 真控制器**上量：`paginate()` 在 total=0 时会走 `$results = $total ? … : collect()`
 * 的短路、**取数查询根本不发**（查询日志里只有 count）⇒ 每条用例先播一行，否则形状断言会以
 * 「没抓到带 limit 的查询」失败（CendPaginationTotalityTest 的类注释里记着同一个坑）。
 * 播种全部包在事务里，tearDown 整笔回滚；只打库名含 test 的库。
 *
 * ⚠ `ticket/list` / `tournament/list` / `chat/messages` 的回包**不带 per_page**（只有
 * items/total/page/last_page）⇒ 回显断言只对另外 4 个端点成立（见 perPageEchoEndpoints()），
 * 不是漏测：那 3 个端点的夹取只体现在 SQL 形状上。
 */
final class PaginationClampSevenEndpointsTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $peerId = 0;
    private int $groupId = 0;

    /** 端点 => [控制器, 方法, 路径, 夹具表, 回包是否带 per_page] */
    private const ENDPOINTS = [
        'game/list'           => [GameController::class, 'list', '/api/v1/game/list', 'game', true],
        'wallet/transactions' => [WalletController::class, 'transactions', '/api/v1/wallet/transactions', 'transaction', true],
        'notification/list'   => [NotificationController::class, 'list', '/api/v1/notification/list', 'notification', true],
        'ticket/list'         => [TicketController::class, 'list', '/api/v1/ticket/list', 'ticket', false],
        'tournament/list'     => [TournamentController::class, 'list', '/api/v1/tournament/list', 'tournament', false],
        'chat/messages'       => [ChatController::class, 'messages', '/api/v1/chat/messages/{peer}', 'message', false],
        'groups/members'      => [GroupController::class, 'members', '/api/v1/groups/{group}/members', 'group', true],
    ];

    /** @return array<string, array{0: class-string, 1: string, 2: string, 3: string, 4: bool}> */
    public static function endpoints(): array
    {
        return self::ENDPOINTS;
    }

    /** 回包带 per_page 的那 4 个（回显口径得单独量） */
    public static function perPageEchoEndpoints(): array
    {
        return array_filter(self::ENDPOINTS, static fn (array $e): bool => $e[4]);
    }

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 命中行会走 encodeId()，先起容器绑定（同 SearchPaginationTotalityTest）
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

        $this->userId  = (int) SnowflakeService::generate();
        $this->peerId  = (int) SnowflakeService::generate();
        $this->groupId = 0;

        Db::beginTransaction();

        // tournament 端点先过 FeatureFlag 闸（默认 off ⇒ 根本不走到分页），夹具里打开
        Db::table('platform_config')->updateOrInsert(
            ['group' => 'feature', 'key' => 'tournament'],
            [
                'id'          => SnowflakeService::generate(),
                'value'       => 'on',
                'type'        => 'string',
                'description' => 'test fixture',
            ]
        );
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    /** 上界：`?per_page=1000000` 曾能一次拉走整表（连带 with/withCount 的关联） */
    #[Test]
    #[DataProvider('endpoints')]
    public function upperBoundIsClampedToOneHundred(string $controller, string $method, string $path, string $table): void
    {
        $this->seed($table);
        [$select, $body] = $this->callWithLog($controller, $method, $path, 1000000);

        $this->assertSame(0, $body['code'] ?? -1, "{$path}: 端点未成功：" . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertNotSame('', $select, "{$path}: 没抓到分页取数查询（含 offset 的那条），SQL 断言无从谈起");
        $this->assertMatchesRegularExpression('/\blimit 100\b/i', $select,
            "{$path} 的 per_page 未夹到上界 100 ⇒ `?per_page=1000000` 能把整表拉进内存。实际 SQL：{$select}");
    }

    /**
     * 下界：`Builder::limit()` 对负值是「忽略」⇒ 取数查询没有 limit 子句、只剩 `offset 0` ⇒
     * MySQL 1064（`game/list` 在公开组，匿名可触发）。只写 `min(100, …)` 挡不住这条路。
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function negativePerPageCannotDropTheLimitClause(string $controller, string $method, string $path, string $table): void
    {
        $this->seed($table);
        [$select, $body] = $this->callWithLog($controller, $method, $path, -1);

        $this->assertSame(0, $body['code'] ?? -1, "{$path}: 端点未成功：" . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertNotSame('', $select, "{$path}: 没抓到分页取数查询（含 offset 的那条），SQL 断言无从谈起");
        $this->assertMatchesRegularExpression('/\blimit 1\b/i', $select,
            "{$path}: per_page=-1 时取数查询没有 limit 子句、只剩 `offset 0`（Laravel 对负 limit 是「忽略」）"
            . "⇒ MySQL 报 1064、端点是 500。实际 SQL：{$select}");
    }

    /** 回包与 SQL 同口径：回显的必须是**夹后**的值，不能 SQL 夹了、回包还吐请求原值 */
    #[Test]
    #[DataProvider('perPageEchoEndpoints')]
    public function perPageEchoMatchesTheAppliedBound(string $controller, string $method, string $path, string $table): void
    {
        $this->seed($table);

        [, $body] = $this->callWithLog($controller, $method, $path, 1000000);
        $this->assertSame(100, $body['data']['per_page'] ?? null,
            "{$path}: 请求 per_page=1000000 时回包应回显夹后的 100");

        [, $body] = $this->callWithLog($controller, $method, $path, -1);
        $this->assertSame(1, $body['data']['per_page'] ?? null,
            "{$path}: 请求 per_page=-1 时回包应回显夹后的 1");
    }

    // ---------------------------------------------------------------- 夹具 / 调用

    /** 各表只给 NOT NULL 且无默认值的列（DDL 见 install/install.sql；无 FK 约束） */
    private function seed(string $table): void
    {
        $id = (int) SnowflakeService::generate();

        match ($table) {
            'game' => Db::table('game')->insert([
                'id' => $id, 'name' => 'PC ' . $id, 'slug' => 'pc-' . $id, 'status' => 1,
            ]),
            'transaction' => Db::table('transaction')->insert([
                'id' => $id, 'user_id' => $this->userId, 'type' => 'deposit',
                'amount' => '1.00000000', 'balance_after' => '1.00000000',
            ]),
            'notification' => Db::table('notification')->insert([
                'id' => $id, 'user_id' => $this->userId, 'title' => 'PC', 'content' => 'PC',
            ]),
            'ticket' => Db::table('ticket')->insert([
                'id' => $id, 'user_id' => $this->userId, 'content' => 'PC',
            ]),
            // status=1 且 start<=now<=end：默认 status=active 的过滤条件必须命中一行
            'tournament' => Db::table('tournament')->insert([
                'id' => $id, 'name' => 'PC ' . $id, 'slug' => 'pc-' . $id,
                'start_at' => date('Y-m-d H:i:s', time() - 86400),
                'end_at'   => date('Y-m-d H:i:s', time() + 86400),
                'status'   => 1,
            ]),
            'message' => Db::table('message')->insert([
                'id' => $id, 'from_user_id' => $this->userId, 'to_user_id' => $this->peerId, 'content' => 'PC',
            ]),
            'group' => (function () use ($id): void {
                Db::table('group')->insert([
                    'id' => $id, 'type' => 'guild', 'name' => 'PC ' . $id, 'owner_id' => $this->userId,
                ]);
                Db::table('group_member')->insert([
                    'id' => SnowflakeService::generate(), 'group_id' => $id, 'user_id' => $this->userId,
                ]);
                $this->groupId = $id;
            })(),
        };
    }

    /** 路径里的动态段（hashid）在这里落值 */
    private function pathFor(string $path): string
    {
        return str_replace(
            ['{peer}', '{group}'],
            [HashidsService::encode($this->peerId), HashidsService::encode($this->groupId)],
            $path
        );
    }

    /**
     * 跑一次端点，返回 [分页取数查询, 回包]。
     *
     * ⚠ 分页取数查询按**含 `offset`** 认（page=1 ⇒ `offset 0`），不按「含 limit」认 ——
     * per_page 为负时 Laravel 会把 limit 子句**整个丢掉**、只剩 `offset 0`，那正是要抓的形状；
     * 而按「含 limit」或「取第一条 `select *`」认都会先抓到 `Group::find()` / `PlatformConfig::get()`
     * 的 `... limit 1`（实测：tournament 与 groups 两条用例就是这么假红的）。
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function callWithLog(string $controller, string $method, string $path, int $perPage): array
    {
        // chat/messages 与 groups/{hashid}/members 的路由参数是 hashid，按真实路由顺序补第二实参
        $args = [];
        if (str_contains($path, '{peer}')) {
            $args[] = HashidsService::encode($this->peerId);
        }
        if (str_contains($path, '{group}')) {
            $args[] = HashidsService::encode($this->groupId);
        }

        $path    = $this->pathFor($path) . "?page=1&per_page={$perPage}";
        $request = new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $this->userId;

        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $response = (new $controller())->{$method}($request, ...$args);
            $log      = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $selects = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'offset') !== false
        ));

        return [
            $selects[0] ?? '',
            json_decode((string) $response->rawBody(), true) ?? [],
        ];
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
