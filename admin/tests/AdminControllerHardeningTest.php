<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\AnalyticsController;
use app\admin\v1\controller\ExportController;
use app\admin\v1\controller\ImportController;
use app\admin\v1\controller\PermissionController;
use app\admin\v1\controller\ReportController;
use app\admin\v1\controller\RoleController;
use app\model\AdminPermission as AdminPermissionModel; // 中间件那个同名类不含 forgetByRole，这里只用模型查库
use app\model\OperationLog;
use common\HashidsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;
use Throwable;

/**
 * 管理端控制器加固的三组判据（都是「读数会静默变坏」的地方，不是形状检查）：
 *   ① 查询预算：economy() 的 1+2N、summary() 的 4 对同 where 查询；
 *   ② 导出截断：上限 +1 的信标必须真的判定出「超了」，且提示要写进产物；
 *   ③ 导入边界：超行数必须**拒绝**（不是静默只导前 N 行）、查重用批量而不是逐行。
 *
 * 私有 id 段 990002000+/990003000+/990004000+，避开其它用例已占用的号段。
 * 所有写库动作都在事务里，finally 一律 rollBack（并行跑套件时不能留残留）。
 */
class AdminControllerHardeningTest extends TestCase
{
    private const CURRENCY_ID = 990003001;
    private const GAME_ID     = 990003002;
    private const RANGE_FROM  = 990002000;   // operation_log 私有号段起点
    private const DEPOSIT_ID  = 990003101;
    private const WITHDRAW_ID = 990003201;
    private const EXISTING_NAME = 'probe_imp_existing';
    // RBAC 缓存失效用例的私有号段（990005xxx）
    private const CACHE_ADMIN = 990005001;
    private const CACHE_ROLE  = 990005011;
    private const CACHE_PERM  = 990005021;
    private const CACHE_CHILD = 990005022;
    private const OP_PASSWORD = 'Aa123456';

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    // ============================================================
    // ① 查询预算
    // ============================================================

    /**
     * economy() 每个币种原先各打 2 条 SUM，现在只打 1 条 GROUP BY。
     * 断言的是**聚合读数本身**与查询条数两条：只钉条数会漏掉「一条查询但算错」。
     */
    #[Test]
    public function economyAggregatesAllCurrenciesInOneQuery(): void
    {
        Db::beginTransaction();
        try {
            Db::table('game_currency')->insert([
                'id' => self::CURRENCY_ID, 'game_id' => self::GAME_ID,
                'name' => 'PROBECOIN', 'symbol' => 'PBC',
            ]);
            foreach ([[990003010, 'in', '100.0000'], [990003011, 'in', '20.0000'], [990003012, 'out', '30.0000']] as [$id, $dir, $amt]) {
                Db::table('exchange_record')->insert([
                    'id' => $id, 'user_id' => 990003099, 'game_id' => self::GAME_ID,
                    'currency_id' => self::CURRENCY_ID, 'direction' => $dir,
                    'platform_amount' => $amt, 'game_amount' => $amt, 'rate' => '1.00000000',
                ]);
            }

            $response = (new AnalyticsController())->economy($this->get('/admin/v1/analytics/economy'));
            $queries  = $this->captureQueries(fn () => (new AnalyticsController())->economy($this->get('/admin/v1/analytics/economy')));

            $grouped = array_values(array_filter(
                $queries,
                static fn (string $sql): bool => str_contains($sql, 'exchange_record') && stripos($sql, 'group by') !== false
            ));
            $this->assertCount(1, $grouped, '每个币种一条 SUM 的 1+2N 必须只剩一条 GROUP BY');

            $body = json_decode((string) $response->rawBody(), true);
            $mine = array_values(array_filter(
                $body['data']['currencies'] ?? [],
                static fn (array $c): bool => $c['currency'] === 'PROBECOIN'
            ));
            $this->assertCount(1, $mine, 'PROBECOIN 应恰好出现一次');
            $this->assertSame('120.0000', $mine[0]['total_minted']);
            $this->assertSame('30.0000', $mine[0]['total_burned']);
            $this->assertSame('90.00000000', $mine[0]['circulation']);
            $this->assertSame('75.00%', $mine[0]['inflation_rate']);
        } finally {
            Db::rollBack();
        }
    }

    /**
     * summary() 的 deposit/withdraw 各由「SUM 一条 + COUNT 一条」合成一条 selectRaw。
     * 判据是 values 与查询条数**同时**成立：合并后 where 写错会先体现在读数上。
     */
    #[Test]
    public function summaryMergesAggregatesAndKeepsValues(): void
    {
        $cacheKey = 'report:summary:2020-01-01:2020-01-02';
        $this->forget($cacheKey);

        Db::beginTransaction();
        try {
            Db::table('deposit_order')->insert([
                ['id' => self::DEPOSIT_ID, 'order_no' => 'PROBE-D1', 'user_id' => 990003100, 'amount' => '10.0000',
                 'platform_amount' => '10.0000', 'status' => 'confirmed', 'created_at' => '2020-01-01 10:00:00'],
                // 同窗口但 pending：不得计入（合并查询时最容易丢的就是这个 where）
                ['id' => self::DEPOSIT_ID + 1, 'order_no' => 'PROBE-D2', 'user_id' => 990003100, 'amount' => '99.0000',
                 'platform_amount' => '99.0000', 'status' => 'pending', 'created_at' => '2020-01-01 11:00:00'],
                // 窗口外：不得计入
                ['id' => self::DEPOSIT_ID + 2, 'order_no' => 'PROBE-D3', 'user_id' => 990003100, 'amount' => '7.0000',
                 'platform_amount' => '7.0000', 'status' => 'confirmed', 'created_at' => '2021-06-01 10:00:00'],
            ]);
            Db::table('withdraw_order')->insert([
                ['id' => self::WITHDRAW_ID, 'order_no' => 'PROBE-W1', 'user_id' => 990003100,
                 'platform_amount' => '4.0000', 'status' => 'approved', 'created_at' => '2020-01-02 10:00:00'],
                ['id' => self::WITHDRAW_ID + 1, 'order_no' => 'PROBE-W2', 'user_id' => 990003100,
                 'platform_amount' => '88.0000', 'status' => 'rejected', 'created_at' => '2020-01-02 11:00:00'],
            ]);

            // 必须捕获**第一次**（缓存未命中）调用的查询：第二次会走 Redis 缓存，一条 SQL 都没有
            $response = null;
            $queries  = $this->captureQueries(function () use (&$response): void {
                $response = (new ReportController())->summary($this->get('/admin/v1/report/summary?start=2020-01-01&end=2020-01-02'));
            });

            $count = static fn (array $log, string $table): int => count(array_filter(
                $log,
                static fn (string $sql): bool => str_contains($sql, $table) && stripos($sql, 'select') === 0
            ));
            $this->assertSame(1, $count($queries, 'deposit_order'), '充值金额与笔数应合成一条 selectRaw');
            $this->assertSame(1, $count($queries, 'withdraw_order'), '提现金额与笔数应合成一条 selectRaw');

            $body = json_decode((string) $response->rawBody(), true);
            $data = $body['data'];
            $this->assertSame('10.0000', $data['deposit_amount'], '只算 confirmed、只算窗口内');
            $this->assertSame(1, $data['deposit_count']);
            $this->assertSame('4.0000', $data['withdraw_amount'], '只算 approved/completed');
            $this->assertSame(1, $data['withdraw_count']);
            $this->assertArrayHasKey('play_count', $data, '合并后 7 个键一个都不能少');
            $this->assertArrayHasKey('exchange_amount', $data);
            $this->assertArrayHasKey('new_users', $data);
        } finally {
            Db::rollBack();
            $this->forget($cacheKey);
        }
    }

    // ============================================================
    // ② 导出截断
    // ============================================================

    /**
     * 上限 +1 的信标必须能同时判定两侧：10001 行 ⇒ 截断且只出 10000 行；
     * 恰好 10000 行 ⇒ **不算截断**（这正是「多取一行」存在的理由）。
     *
     * ⚠ **本用例故意很慢（实测约 15s，占全套件的大头）：要真灌 10001 行才能量到「多取一行」这条边界。**
     *   别当低效测试删掉 / 别把行数调小 —— 它是本仓**唯一**能观察「上限 +1 信标」的钉子；
     *   行数一缩，测的就变成「远未触顶的正常路径」，两侧断言会一起退化成恒真。
     */
    #[Test]
    public function exportFetchLimitedStopsAtCap(): void
    {
        Db::beginTransaction();
        try {
            $this->seedOperationLogs(10001);

            $limit = self::RANGE_FROM + 10000;
            // select('id')：只钉「条数 + 信标」，少灌一列就少一分水合开销（本机 1 万条模型约 5s）
            $query = OperationLog::where('id', '>=', self::RANGE_FROM)->where('id', '<=', $limit)->select('id');

            [$rows, $truncated] = $this->callPrivate('fetchLimited', $query);
            $this->assertTrue($truncated, '10001 行必须判为截断');
            $this->assertCount(10000, $rows, '多取的第 10001 行不得出现在产物里');

            Db::table('operation_log')->where('id', $limit)->delete();

            [$rows2, $truncated2] = $this->callPrivate('fetchLimited', $query);
            $this->assertFalse($truncated2, '恰好 10000 行不算截断（边界方向必须两条都钉）');
            $this->assertCount(10000, $rows2);
        } finally {
            Db::rollBack();
        }
    }

    /** 截断提示要写进产物本身：只写响应头/日志，看文件的人永远不知道少了行 */
    #[Test]
    public function markTruncatedWritesVisibleCell(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $this->callPrivate('markTruncated', $sheet, 5);

        $value = (string) $sheet->getCell('A5')->getValue();
        $this->assertStringContainsString('10000', $value, '提示里要写清上限是多少');
        $this->assertNotSame('', $value);
        $font = $sheet->getStyle('A5')->getFont();
        $this->assertTrue($font->getBold(), '提示行要加粗');
        $this->assertSame('C00000', $font->getColor()->getRGB(), '提示行要标红');
    }

    // ============================================================
    // ③ 导入边界
    // ============================================================

    /** 超过行数上限必须整单拒绝（422），不能「导了前 1000 行还报成功」 */
    #[Test]
    public function importRejectsSheetAboveRowCap(): void
    {
        $rows = [['username', 'password', 'real_name', 'phone', 'email', 'status']];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['probe_cap_' . $i, 'Aa123456', 'n', '', '', 1];
        }

        $response = (new ImportController())->users($this->multipartUpload($this->xlsx($rows), 'probe_cap.xlsx'));
        $body     = json_decode((string) $response->rawBody(), true);

        $this->assertSame(422, $body['code'] ?? null, '1001 行（含表头）应被拒绝');
        $this->assertStringContainsString('1000', (string) $body['message'], '报错要说清上限');
    }

    /**
     * 查重从「逐行 exists()」换成一次 whereIn，语义必须一模一样：
     * 库里已存在 ⇒ 失败；**同一文件内重复 ⇒ 也算已存在**（原实现第二行会查到第一行刚插入的记录）。
     */
    #[Test]
    public function importBatchesUsernameLookupAndKeepsDuplicateSemantics(): void
    {
        Db::beginTransaction();
        try {
            Db::table('admin_user')->insert([
                'id' => 990004001, 'username' => self::EXISTING_NAME, 'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
                'real_name' => 'seeded', 'status' => 1,
            ]);

            $rows = [
                ['username', 'password', 'real_name', 'phone', 'email', 'status'],
                [self::EXISTING_NAME, 'Aa123456', 'dup-in-db', '', '', 1],
                ['probe_imp_new', 'Aa123456', 'fresh', '', '', 1],
                ['probe_imp_new', 'Aa123456', 'dup-in-file', '', '', 1],
            ];

            $request  = $this->multipartUpload($this->xlsx($rows), 'probe_imp.xlsx');
            $response = (new ImportController())->users($request);
            $queries  = $this->captureQueries(fn () => (new ImportController())->users($this->multipartUpload($this->xlsx($rows), 'probe_imp.xlsx')));

            $lookups = array_values(array_filter(
                $queries,
                static fn (string $sql): bool => stripos($sql, 'select') === 0 && str_contains($sql, 'admin_user')
            ));
            $this->assertCount(1, $lookups, '用户名查重应是一次批量查询，不是逐行 exists()');

            $body = json_decode((string) $response->rawBody(), true);
            $this->assertSame(3, $body['data']['total']);
            $this->assertSame(1, $body['data']['success'], '只有 probe_imp_new 的第一行该被导入');
            $this->assertSame(2, $body['data']['failed']);
            $this->assertFalse($body['data']['errors_truncated']);
            $this->assertSame(1, (int) Db::table('admin_user')->where('username', 'probe_imp_new')->count());
        } finally {
            Db::rollBack();
        }
    }

    // ============================================================
    // ④ RBAC 缓存失效（RoleController / PermissionController）
    // ============================================================

    /**
     * 改 / 删角色后 `perm:{adminId}` 必须当场失效。
     *
     * 两条边都钉：update（减权限）与 destroy（角色没了）。destroy 那条**同时钉死调用时机**——
     * `forgetByRole` 是按 `admin_user_role` 反查名单的，放到 `users()->detach()` 之后就查空表、
     * 静默空转，缓存里那份旧权限集最长还能用 60 秒（＝已吊销的权限仍然打得通端点）。
     */
    #[Test]
    public function roleUpdateAndDestroyClearPermissionCache(): void
    {
        Db::beginTransaction();
        try {
            $this->seedRbac();
            $key = 'perm:' . self::CACHE_ADMIN;

            Redis::setex($key, 60, '["probe.stale"]');
            $ok = $this->json((new RoleController())->update(
                $this->jsonBody('PUT', ['permission_ids' => []]),
                $this->hash(self::CACHE_ROLE)
            ));
            $this->assertSame(0, (int) $ok['code'], 'update 应成功：' . json_encode($ok));
            $this->assertFalse((bool) Redis::get($key), '改角色权限后必须清 perm:{adminId}');

            Redis::setex($key, 60, '["probe.stale"]');
            $del = $this->json((new RoleController())->destroy(
                $this->jsonBody('DELETE', ['password' => self::OP_PASSWORD]),
                $this->hash(self::CACHE_ROLE)
            ));
            $this->assertSame(0, (int) $del['code'], 'destroy 应成功：' . json_encode($del));
            $this->assertFalse((bool) Redis::get($key), '删角色后必须清 perm:{adminId}（且必须在 detach 之前调）');
            $this->assertSame(0, (int) Db::table('admin_user_role')->where('role_id', self::CACHE_ROLE)->count());
        } finally {
            Db::rollBack();
            $this->forget('perm:' . self::CACHE_ADMIN);
        }
    }

    /**
     * 删权限要清「持有该权限**或它的子权限**的角色」名下管理员的缓存。
     *
     * 这里刻意让角色只挂**子**权限（父权限只做层级）：只按 `$perm->roles()` 收名单会漏掉这条通路，
     * 而中间件那侧是按 `permission_id ∈ {自身 ∪ 子权限}` 反查的——两侧名单必须对齐。
     */
    #[Test]
    public function permissionDestroyClearsHoldersOfChildPermissionsToo(): void
    {
        Db::beginTransaction();
        try {
            $this->seedRbac();
            Db::table('admin_role_permission')->where('role_id', self::CACHE_ROLE)->delete();
            Db::table('admin_role_permission')->insert(['role_id' => self::CACHE_ROLE, 'permission_id' => self::CACHE_CHILD]);
            $key = 'perm:' . self::CACHE_ADMIN;

            Redis::setex($key, 60, '["probe.child"]');
            $res = $this->json((new PermissionController())->destroy(
                $this->jsonBody('DELETE', ['password' => self::OP_PASSWORD]),
                $this->hash(self::CACHE_PERM)
            ));
            $this->assertSame(0, (int) $res['code'], 'destroy 应成功：' . json_encode($res));
            $this->assertFalse((bool) Redis::get($key), '删权限（含级联子权限）后必须清持有者缓存');
            $this->assertSame(
                0,
                (int) AdminPermissionModel::whereIn('id', [self::CACHE_PERM, self::CACHE_CHILD])->count(),
                '父权限与级联的子权限都应删除'
            );
        } finally {
            Db::rollBack();
            $this->forget('perm:' . self::CACHE_ADMIN);
        }
    }

    // ============================================================
    // 工具
    // ============================================================

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(ExportController::class, $method))->invoke(new ExportController(), ...$args);
    }

    /** 私有号段内批量灌 operation_log（1000 行一批，别用 10001 条单行 insert） */
    private function seedOperationLogs(int $count): void
    {
        $batch = [];
        for ($i = 0; $i < $count; $i++) {
            $batch[] = ['id' => self::RANGE_FROM + $i, 'action' => 'probe.export'];
            if (count($batch) === 1000) {
                Db::table('operation_log')->insert($batch);
                $batch = [];
            }
        }
        if ($batch) {
            Db::table('operation_log')->insert($batch);
        }
    }

    /** 跑一次 $fn，返回本次调用产生的 SQL 列表（先 flush：query log 是只增的缓冲） */
    private function captureQueries(callable $fn): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $fn();
            return array_column($connection->getQueryLog(), 'query');
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    private function get(string $uri): Request
    {
        return new Request("GET {$uri} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /** PUT/DELETE + JSON body（webman 按 content-type 解析 body，与 HTTP 方法无关）；adminId 供 confirmPassword 用 */
    private function jsonBody(string $method, array $body): Request
    {
        $encoded = (string) json_encode($body, JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "{$method} /admin/v1/probe HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );
        $request->adminId = self::CACHE_ADMIN;

        return $request;
    }

    private function hash(int $id): string
    {
        return HashidsService::encode($id);
    }

    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    /** 一个管理员 + 一个角色（挂父权限）+ 父/子两条权限，全部落中间表 */
    private function seedRbac(): void
    {
        Db::table('admin_user')->insert([
            'id' => self::CACHE_ADMIN, 'username' => 'probe_hardening_admin',
            'password' => password_hash(self::OP_PASSWORD, PASSWORD_BCRYPT), 'real_name' => 'probe', 'status' => 1,
        ]);
        Db::table('admin_role')->insert([
            'id' => self::CACHE_ROLE, 'name' => 'probe-role', 'slug' => 'probe-role-hardening', 'status' => 1,
        ]);
        Db::table('admin_permission')->insert([
            ['id' => self::CACHE_PERM, 'parent_id' => 0, 'name' => 'probe-perm', 'slug' => 'probe.perm', 'type' => 3],
            ['id' => self::CACHE_CHILD, 'parent_id' => self::CACHE_PERM, 'name' => 'probe-child', 'slug' => 'probe.child', 'type' => 3],
        ]);
        Db::table('admin_user_role')->insert(['user_id' => self::CACHE_ADMIN, 'role_id' => self::CACHE_ROLE]);
        Db::table('admin_role_permission')->insert(['role_id' => self::CACHE_ROLE, 'permission_id' => self::CACHE_PERM]);
    }

    private function forget(string $key): void
    {
        try {
            Redis::del($key);
        } catch (Throwable) {
            // Redis 不可用时 summary 本来就直查库，没有缓存可清
        }
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $tmp = (string) tempnam(sys_get_temp_dir(), 'probe_xlsx_');
        (new Xlsx($spreadsheet))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        $spreadsheet->disconnectWorksheets();

        return $bytes;
    }

    private function multipartUpload(string $bytes, string $filename): Request
    {
        $boundary = '----probe' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
            . "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n"
            . $bytes . "\r\n--{$boundary}--\r\n";

        return new Request(
            "POST /admin/v1/import/users HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: multipart/form-data; boundary={$boundary}\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body
        );
    }
}
