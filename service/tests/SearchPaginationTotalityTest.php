<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\SearchController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * /api/v1/search 的分页全序：补上 `order by sort asc, id desc`。
 *
 * 一、为什么必须有序：`forPage()` 生成的是 `limit N offset M`，而**没有 order by 时 SQL 不保证行序**
 * —— 行序由本次访问路径（全表扫 / idx_status / idx_sort）决定，两次请求可以不一样。翻页的后果是
 * 同一行在两页里各出现一次、另一行任何一页都看不到，**而 total 仍然自洽**，客户端只会觉得"结果怪"。
 *
 * 二、键为什么取 sort asc, id desc 而不是裸 id desc：查询主体是 `common\model\Game`，同一模型的
 * 列表端点 GameController::list 用的就是 `sort asc, id desc`（sort 是运营可配的展示权重，注释写着
 * "越小越靠前"）⇒ 搜索结果与游戏大厅的先后一致。id 是主键，`(sort, id)` 必然是**全序**，
 * 只按 sort 排是**偏序**（同 sort 的行仍未定义次序），那样翻页照样能重能漏。
 *
 * ⚠ 两颗钉子的分工（照 WalletTransactionsOrderingTest 的实测口径）：
 *   - `searchSelectIsTotallyOrderedBySortThenId` 读**生成的 SQL**，优化器无关，是精确钉；
 *   - `sameSortRowsArePagedWithoutRepeatOrSkip` 读**行为**（同 sort 多行翻页），是 lead 要的那颗；
 *     它对本机"完全没有 order by"这一变异确实会红（实测：无序时 InnoDB 按主键升序回行，
 *     与期望的 id desc 相反），但**若将来优化器改用 idx_sort 回行就不再必然红** ⇒ 判缺失看前者。
 *     ⚠ 实测读数：去掉 order by 后，本用例里**"不重"那条断言（6 个 id 互异）仍是绿的** ——
 *     静态表上单趟扫描本来就不会重复行，红的是**次序**断言。⇒ 只写"不重不漏"是不够的，
 *     必须断言拼页结果等于声明的全序（`sort asc, id desc`）。
 *
 * ⚠ 本端点用 `forPage()->get()` 而不是 `paginate()`，所以**空结果也会发取数查询**、没有
 * `$total ? ... : collect()` 那条短路（CendPaginationTotalityTest 的类注释里记着那个坑）。
 * 形状用例故意不播种，顺便把这句话钉住。
 *
 * ⚠ per_page 夹取的**两个半边都是承重的**（`Builder::limit()` 是 `if ($value >= 0)` ⇒ 对负值**忽略**，
 * 既不再落 0 也不报错，而 `offset` 照发）：`min(100, …)` 挡 `?per_page=1000000` 的整表拉取；
 * `max(1, …)` 挡 `?per_page=-1` ⇒ `... offset 0`（无 limit）⇒ MySQL 1064 ⇒ **匿名可打的 500**。
 * 两条各一颗钉子，都在 SQL 形状面（行为面看不见，见上）。
 *
 * ⚠ game_game 无软删；`slug` 是唯一键 ⇒ 播种行必须带随机 token，否则撞 uk_slug。
 */
final class SearchPaginationTotalityTest extends TestCase
{
    private static bool $booted = false;

    /** 随机关键词：保证命中的只有本用例播种的行（真库里 status=1 的游戏不受影响） */
    private string $token = '';

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 命中行会走 encodeId()，先起容器绑定
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

        $this->token = 'zq' . bin2hex(random_bytes(6));
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
    public function searchSelectIsTotallyOrderedBySortThenId(): void
    {
        $select = $this->dataSelect(1, 2);

        $this->assertMatchesRegularExpression(
            '/order by\s+`?sort`?\s+asc\s*,\s*`?id`?\s+desc/i',
            $select,
            '搜索结果没有 `sort asc, id desc` 全序：limit/offset 下的行序未定义，翻页会重复或漏行。'
            . "实际 SQL：{$select}"
        );
    }

    #[Test]
    public function sameSortRowsArePagedWithoutRepeatOrSkip(): void
    {
        // sort=7 四行（"同一排序键"＝M6 里"同一秒"的对应物），sort=3 两行，验两段都按 id desc
        $low  = [$this->seed(3), $this->seed(3)];
        $high = [$this->seed(7), $this->seed(7), $this->seed(7), $this->seed(7)];
        rsort($low);
        rsort($high);                       // sort 越小越靠前；同 sort 内 id 大者在前
        $expected = array_merge($low, $high);

        $seen = [];
        for ($page = 1; $page <= 3; $page++) {
            $body = $this->call($page, 2);

            $this->assertSame(0, $body['code'] ?? -1,
                "第 {$page} 页端点未成功：" . json_encode($body, JSON_UNESCAPED_UNICODE));
            $this->assertSame(6, $body['data']['total'] ?? null,
                "第 {$page} 页 total 应为播种的 6 行（真库里不该有别的行命中这个随机 token）");

            foreach ($body['data']['list'] as $row) {
                $seen[] = (int) HashidsService::decode((string) $row['id']);
            }
        }

        $this->assertSame(6, count(array_unique($seen)),
            '翻页出现重复行：行序未定义时同一行会在两页里各出现一次。实际看到的 id：' . implode(',', $seen));
        $this->assertSame($expected, $seen,
            '翻页次序不是 sort asc, id desc（或漏了行）。实际：' . implode(',', $seen)
            . ' 期望：' . implode(',', $expected));
    }

    /** 上界：本端点在**公开组、无鉴权** ⇒ `?per_page=1000000` 是匿名可触发的整表拉取 */
    #[Test]
    public function perPageIsCappedAtOneHundred(): void
    {
        $select = $this->dataSelect(1, 1000000);

        $this->assertMatchesRegularExpression('/\blimit\s+100\b/i', $select,
            "per_page 未夹到上界 100 ⇒ 匿名 `?per_page=1000000` 能把整表拉进内存。实际 SQL：{$select}");
        $this->assertSame(100, $this->call(1, 1000000)['data']['per_page'] ?? null,
            '回包 per_page 应回显**夹后**的 100（SQL 夹了、回包吐请求原值＝两处口径不一致）');
    }

    /**
     * 下界：`Builder::limit()` 对负值是**忽略**（`vendor/illuminate/database/Query/Builder.php` 的
     * `if ($value >= 0)`，不抛错也不落 0），而 `offset` 照发 ⇒ 生成的是 `... offset 0`（**没有 limit**）。
     * MySQL 里 OFFSET 必须与 LIMIT 配对 ⇒ **1064 语法错** ⇒ 未捕获 QueryException ⇒
     * **匿名一个 `?per_page=-1` 就能打出 500**（实测原文见断言消息；我原先猜"无 limit ⇒ 整表被拉走"，
     * 是这次变异量的读数纠正过来的 —— `offset` 仍在，所以不是拉全表而是报错）。
     * ⇒ 只写 `min(100, …)` 挡不住这条路，`max(1, …)` 是承重的半边。
     */
    #[Test]
    public function negativePerPageCannotDropTheLimitClause(): void
    {
        $select = $this->dataSelect(1, -1);

        $this->assertMatchesRegularExpression('/\blimit\s+1\b/i', $select,
            "per_page=-1 时取数查询没有 limit 子句、只剩 `offset 0`（Laravel 对负 limit 是「忽略」而非报错）"
            . "⇒ MySQL 报 1064、端点是 500。实际 SQL：{$select}");
        $this->assertSame(1, $this->call(1, -1)['data']['per_page'] ?? null,
            '回包 per_page 应回显**夹后**的 1');
    }

    /**
     * 跑一次端点，返回**取数查询**（`select * from game_game …`；count 查询是 `select count(*)`）。
     * ⚠ 这里**不能**按「含 limit」筛：per_page 为负时 Laravel 会把 limit 子句**整个丢掉**，
     * 那种查询恰恰是要抓的（见 negativePerPageCannotDropTheLimitClause）。
     */
    private function dataSelect(int $page, int $perPage): string
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $this->call($page, $perPage);
            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $selects = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos(ltrim($sql), 'select *') === 0
        ));
        $this->assertNotSame([], $selects,
            '没抓到取数查询（select *），SQL 断言无从谈起（查询日志没生效？q 为空走了早返回？）');

        return $selects[0];
    }

    /** @return array<string, mixed> */
    private function call(int $page, int $perPage): array
    {
        $path = "/api/v1/search?q={$this->token}&page={$page}&per_page={$perPage}";
        $request = new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");

        $response = (new SearchController())->search($request);

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function seed(int $sort): int
    {
        $id = (int) SnowflakeService::generate();
        Db::table('game')->insert([
            'id'     => $id,
            'name'   => 'T ' . $this->token . ' ' . $id,
            'slug'   => 'slug-' . $this->token . '-' . $id,
            'status' => 1,
            'sort'   => $sort,
        ]);

        return $id;
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
