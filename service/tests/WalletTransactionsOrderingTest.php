<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WalletController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * GET /api/v1/wallet/transactions —— C 端自己的流水列表。
 *
 * 钉的是**翻页次序必须是全序**：`game_transaction.created_at` 是 DATETIME（秒精度，
 * install/install.sql:315 的 DDL），一局游戏成对写 earn/spend 就会**同秒**。只按 created_at
 * 排序时同秒行的先后是 MySQL 的**未定义行为** ⇒ LIMIT/OFFSET 翻页可能让同一行在两页里各出现
 * 一次、另一行谁都看不到，**而 total/last_page 仍然自洽**（账面对不上行）。修法是补第二排序键
 * `id desc`（照 admin 侧 PlatformUserController::transactions 的写法）。
 *
 * ⚠ 两条用例的分工是刻意的，别把前者当钉子：
 *   - pagination...DoNotRepeatOrSkip() 是**端到端性质检查**（能抓分页算错、per_page 回显错、
 *     total 与 per_page 脱钩），但**抓不到「少了 id 第二排序键」** —— 实测删掉第二键后它照样全绿：
 *     本机优化器恰好按主键稳定返回，同秒行的实际先后并不暴露差异。
 *   - ledgerOrderingIsTotal...() 直接断**生成的 SQL**，这条才**能红**且不赌优化器当次选哪条访问路径。
 */
final class WalletTransactionsOrderingTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $otherUserId = 0;

    /**
     * 让 support\Db 指向测试库。
     *
     * tests/bootstrap.php 只把测试库配置写进了一个局部数组；而 support\Db 首次被 autoload 时
     * 其文件尾部的 Webman\Database\Initializer::init(config('database')) 会再建一个 capsule
     * 并 setAsGlobal —— 用的是【开发库】的 config('database')。所以必须先把这次一次性初始化
     * 烧掉，再自己 setAsGlobal，否则查询打的是开发库（同 ExchangeWalletIntegrationTest）。
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

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 响应行要过 encodeId()，该路径依赖 hashids 容器绑定（webman 插件 bootstrap 注册的，
        // PHPUnit 下要手动起；同 SearchFallbackTest）
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();

        $this->userId      = SnowflakeService::generate();
        $this->otherUserId = SnowflakeService::generate();
        foreach ([$this->userId, $this->otherUserId] as $id) {
            Db::table('user')->insert([
                'id'       => $id,
                'username' => 'wallet_tx_' . $id,
                'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
                'status'   => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        parent::tearDown();
    }

    /** 直插一条流水（真表名 game_transaction，前缀由 Db 补）。 */
    private function seed(
        int $userId,
        string $type,
        string $amount,
        string $createdAt = '2026-01-02 03:04:05'
    ): int {
        $id = SnowflakeService::generate();
        Db::table('transaction')->insert([
            'id'            => $id,
            'user_id'       => $userId,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $amount,
            'ref_type'      => '',
            'ref_id'        => 0,
            'remark'        => 'probe',
            'created_at'    => $createdAt,
        ]);

        return $id;
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function page(string $query = '', ?int $userId = null): array
    {
        $request = new Request(
            'GET /api/v1/wallet/transactions' . $query . " HTTP/1.1\r\nHost: localhost\r\n\r\n"
        );
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId ?? $this->userId;

        $response = (new WalletController())->transactions($request);
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    // ============================================================
    // 一、真钉子：翻页次序必须是全序（直接断 SQL）
    // ============================================================

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
        $this->seed($this->userId, 'game_earn', '1.00000000');

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
    // 二、行为层：分页四键自洽 + 三页不重不漏（抓不到第二排序键，见类注释）
    // ============================================================

    #[Test]
    public function paginationKeysAreConsistentAndPagesDoNotRepeatOrSkip(): void
    {
        // 5 条**同一秒**的流水（一局游戏成对写 earn/spend 就会同秒）。
        // ⚠ 本条的「不重不漏」是**端到端性质检查**（能抓分页算错），但**抓不到「少了 id 第二排序键」**：
        //   实测删掉 id 次序后它照样全绿 —— 同秒行的先后是 MySQL 未定义行为，本机恰好按主键稳定返回。
        //   全序由 ledgerOrderingIsTotalSoPagingCannotRepeatRows 直接钉 SQL。
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->seed($this->userId, 'game_earn', '1.0000000' . $i, '2026-01-02 03:04:05');
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
                $seen[] = (int) \common\HashidsService::decode($item['id']);
            }
        }
        sort($seen);
        $expected = $ids;
        sort($expected);
        $this->assertSame($expected, $seen, '三页拼起来必须不重不漏（少了/多了都是排队次序不稳定）');
    }

    #[Test]
    public function onlyTheRequestedUsersRowsComeBack(): void
    {
        $mineA = $this->seed($this->userId, 'game_earn', '1.00000000');
        $mineB = $this->seed($this->userId, 'game_spend', '-2.00000000');
        $this->seed($this->otherUserId, 'deposit', '3.00000000');

        $data = $this->page();
        $this->assertSame(2, $data['total'], '别人的流水被算进来了');
        $returned = array_map(static fn ($item) => (int) \common\HashidsService::decode($item['id']), $data['items']);
        sort($returned);
        $expected = [$mineA, $mineB];
        sort($expected);
        $this->assertSame($expected, $returned, '返回的不是本用户那两条');
    }

    #[Test]
    public function typeFilterNarrowsTheLedgerAndUnknownUserIsEmpty(): void
    {
        $this->seed($this->userId, 'game_earn', '1.00000000');
        $this->seed($this->userId, 'game_spend', '-2.00000000');
        $this->seed($this->userId, 'deposit', '3.00000000');

        $this->assertSame(3, $this->page()['total'], '不带 type 应全量 3 条');

        $filtered = $this->page('?type=game_spend');
        $this->assertSame(1, $filtered['total'], 'type 过滤没生效');
        $this->assertSame(['game_spend'], array_column($filtered['items'], 'type'));

        $unknown = $this->page('', SnowflakeService::generate());
        $this->assertSame(0, $unknown['total']);
        $this->assertSame([], $unknown['items']);
    }

    /**
     * 形状契约：id/ref_id 走 hashid（裸 BIGINT 会让前端 decode 出别的数），
     * amount/balance_after 是字符串（DECIMAL(20,8) 不经 float），created_at 原样吐。
     */
    #[Test]
    public function idsAreHashidsAndAmountsAreStrings(): void
    {
        $refId = SnowflakeService::generate();
        $txId  = SnowflakeService::generate();
        Db::table('transaction')->insert([
            'id'            => $txId,
            'user_id'       => $this->userId,
            'type'          => 'withdraw',
            'amount'        => '-10.00000000',
            'balance_after' => '90.00000000',
            'ref_type'      => 'withdraw',
            'ref_id'        => $refId,
            'remark'        => 'probe',
            'created_at'    => '2026-01-02 03:04:05',
        ]);

        $item = $this->page()['items'][0];

        $this->assertIsString($item['id'], 'id 不是字符串（裸 BIGINT 的典型形状）');
        $this->assertSame($txId, \common\HashidsService::decode($item['id']), 'id 解不回原值');
        $this->assertSame($refId, \common\HashidsService::decode($item['ref_id']), 'ref_id 解不回原值');
        $this->assertIsString($item['amount'], 'amount 必须是字符串，不是 JSON number');
        $this->assertSame('-10.00000000', $item['amount']);
        $this->assertSame('2026-01-02 03:04:05', $item['created_at'], 'created_at 必须原样吐');
    }

    /** ref_id=0 走 null 分支（DDL: ref_id NOT NULL DEFAULT 0）—— 吐 hashid(0) 会渲染出不存在的单据号。 */
    #[Test]
    public function refIdZeroComesBackAsNull(): void
    {
        $this->seed($this->userId, 'deposit', '5.00000000');

        $this->assertNull($this->page()['items'][0]['ref_id'], 'ref_id=0 必须吐 null');
    }
}
