<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\DepositController;
use app\api\v1\controller\ExchangeController;
use app\api\v1\controller\GamePlayLogController;
use app\api\v1\controller\WithdrawController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * C 端四个列表端点的分页收口：**全序排序** + **per_page 上界**。
 *
 * 一、排序：四处的 `created_at` 都是 DATETIME（秒精度），同秒多行是常态（充值/兑换高频同批写入）
 * ⇒ 只按 created_at 排序时同秒行的先后是 MySQL **未定义行为**，LIMIT/OFFSET 翻页会让同一行在
 * 两页里各出现一次、另一行谁都看不到，**而 total/last_page 仍然自洽**。修法是补第二排序键
 * `id desc`，样板与理由注释在 WalletController::transactions。
 *
 * 二、上界：原先 `(int) $request->input('per_page', 20)` 直接进 paginate ⇒ `?per_page=1000000`
 * 可一次把整表拉进内存。上界口径抄同域 WalletService::ledger 的 `max(1, min(100, $limit))`。
 *
 * ⚠ 每条用例只断**生成的 SQL**（排序键）与**回包/回包里的 limit**（上界），不赌优化器当次选哪条
 * 访问路径 —— 同秒行的实际先后在本机恰好按主键稳定，行为面读数看不见「少了第二排序键」
 * （WalletTransactionsOrderingTest 的类注释里有实测结论）。
 *
 * ⚠ 每条 SQL 用例都先播一行：Laravel 的 paginate() 在 total=0 时走
 * `$results = $total ? ...->get() : collect()` 的短路，**取数查询根本不会发** ⇒ 空表下查询日志里
 * 只有 count、没有带 limit 的那条，形状断言会以「没抓到 limit 查询」失败（实测踩过）。
 * 播种行包在事务里，tearDown 整笔回滚。
 */
final class CendPaginationTotalityTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;

    /** 端点 => [控制器, 方法, 表名] */
    private const ENDPOINTS = [
        'deposit/orders'   => [DepositController::class, 'orders', 'deposit_order'],
        'withdraw/orders'  => [WithdrawController::class, 'orders', 'withdraw_order'],
        'exchange/records' => [ExchangeController::class, 'records', 'exchange_record'],
        'game/play-logs'   => [GamePlayLogController::class, 'list', 'game_play_log'],
    ];

    /** @return array<string, array{0: class-string, 1: string, 2: string}> */
    public static function endpoints(): array
    {
        return self::ENDPOINTS;
    }

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 空结果集不会走到 encodeId()；仍照同族用例先起好容器绑定，免得上游播种后本文件突然红
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

        $this->userId = (int) SnowflakeService::generate();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function listIsTotallyOrderedSoPagingCannotRepeatRows(string $controller, string $method, string $table): void
    {
        $this->seed($table);
        $select = $this->limitSelect($controller, $method, '/api/v1/' . str_replace('.', '/', $table));

        $this->assertMatchesRegularExpression(
            '/order by\s+`?created_at`?\s+desc\s*,\s*`?id`?\s+desc/i',
            $select,
            "{$table} 的列表查询缺 id 第二排序键：created_at 是秒精度 DATETIME，同秒多行时翻页次序"
            . '由 MySQL 自行决定（未定义行为），会出现同一行在两页里各出现一次、另一行谁都看不到。'
            . "实际 SQL：{$select}"
        );
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function perPageIsCappedAtTheWalletLedgerBound(string $controller, string $method, string $table): void
    {
        $this->seed($table);
        $select = $this->limitSelect($controller, $method, '/api/v1/' . str_replace('.', '/', $table));

        $this->assertMatchesRegularExpression('/\blimit 100\b/i', $select,
            "{$table} 的 per_page 未夹到上界 100 ⇒ `?per_page=1000000` 能把整表拉进内存。"
            . "实际 SQL：{$select}");
    }

    /**
     * 回包与 SQL 必须同口径：per_page 回显的是**夹过之后**的值，不能 SQL 夹了 100、回包还吐请求原值。
     */
    #[Test]
    #[DataProvider('endpoints')]
    public function perPageEchoMatchesTheAppliedBound(string $controller, string $method, string $table): void
    {
        $response = (new $controller())->{$method}($this->request('/api/v1/' . str_replace('.', '/', $table)));
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1,
            "{$table}: 端点未成功：" . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(100, $body['data']['per_page'] ?? null,
            "{$table}: 请求 per_page=1000000 时回包应回显夹后的 100");
    }

    /** 跑一次端点，返回带 limit 的那条查询（分页的取数查询；count 查询没有 limit） */
    private function limitSelect(string $controller, string $method, string $path): string
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            (new $controller())->{$method}($this->request($path));
            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $selects = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'limit') !== false
        ));
        $this->assertNotSame([], $selects,
            '没抓到带 limit 的查询，SQL 断言无从谈起（查询日志没生效？端点没走到 paginate？）');

        return $selects[0];
    }

    private function request(string $path): Request
    {
        $request = new Request("GET {$path}?page=1&per_page=1000000 HTTP/1.1\r\nHost: localhost\r\n\r\n");
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $this->userId;

        return $request;
    }

    /** 各表只给 NOT NULL 且无默认值的列（DDL 见 install/install.sql） */
    private function seed(string $table): void
    {
        $id     = (int) SnowflakeService::generate();
        $common = ['id' => $id, 'user_id' => $this->userId, 'created_at' => '2026-01-02 03:04:05'];

        $row = match ($table) {
            'deposit_order' => $common + [
                'order_no' => 'DEP' . $id, 'amount' => '1.0000', 'platform_amount' => '1.0000',
            ],
            'withdraw_order' => $common + [
                'order_no' => 'WTH' . $id, 'platform_amount' => '1.0000',
            ],
            'exchange_record' => $common + [
                'game_id' => 990000201, 'currency_id' => 990000202, 'direction' => 'in',
                'platform_amount' => '1.0000', 'game_amount' => '1.0000', 'rate' => '1.00000000',
            ],
            'game_play_log' => $common + ['game_id' => 990000201, 'action' => 'start'],
        };

        Db::table($table)->insert($row);
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
