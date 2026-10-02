<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\PlatformUserController;
use app\middleware\AdminAuth;
use app\middleware\AdminPermission;
use app\middleware\OperationLog;
use app\model\AdminUser;
use app\model\OperationLog as OperationLogModel;
use common\HashidsService;
use common\model\User;
use common\SnowflakeService;
use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;
use Webman\Middleware;
use Webman\Route;

/**
 * 「显式全号查看」端点的钉子：POST /admin/v1/platform/user/{hashid}/reveal。
 *
 * 为什么不是「GET 详情加 ?reveal=1」：`OperationLog` 只记 POST/PUT/DELETE，
 * 对其余方法在 `app/middleware/OperationLog.php:19-23` **直接早返回** ⇒ 详情（GET）上加开关
 * 在库里**一行都不会留**，「谁在何时看了谁的全号」无从追。做成独立 POST 动作端点后，
 * 留痕完全复用既有中间件，没有第二套日志代码。
 *
 * 四条钉子：
 *  ① 形态：POST 命中、GET 同路径 **不命中**（GET 形态就是"记不到"的那个形状）；
 *     且该路由确实继承 /admin/v1 组的三层（AdminAuth → AdminPermission → OperationLog）。
 *  ② 有 `post.admin/platform/user/reveal` ⇒ 拿到**明文** phone/email/last_login_ip。
 *  ③ 只有 `get.admin/platform/user` ⇒ 403 —— 证明 reveal 权限**不复用**「查看平台用户」。
 *  ④ 留痕：②的那次请求在 `game_operation_log` 里落下**恰好一行**，`user_id` = 操作者、
 *     `path` 含目标 hashid、`input` 含目标的**裸 ID**（`target_user_id` —— path 里的 hashid 反查
 *     绑死在 HASHIDS_SALT 上，salt 一轮换历史行就静默不可解；操作者那列本来就是裸 BIGINT，两列须对称）。
 *     ③被拒时不留行（OperationLog 在 AdminPermission 之后，未执行）。
 *
 * ②③④ **真穿中间件**（链取自框架自己的 `Middleware::getMiddleware()`，不是测试里抄的清单），
 * 不直调控制器 —— 「能不能留痕」正是中间件层的事，直调控制器会把它整个绕过去、给出一条恒绿的假钉子。
 * 装配方向也由 ② 自证：若把 AdminPermission 折到 AdminAuth 之前，`$request->adminId` 还没注入
 * ⇒ 直接 401、拿不到明文、也落不下行，三条断言一起红。
 *
 * 默认路径（不带任何参数 GET 详情）仍脱敏，由**未改动**的
 * `PlatformUserPiiMaskingTest::detailMasksContactFieldsAndLoginIp` 守着，本文件不重复。
 *
 * 变异读数（每条都实跑过：精确串替换、替换前 count(old)==1、跑完 md5 回基线）：
 *  - reveal 的 `'phone' => (string) $user->phone` 改成 `'***'` ⇒ ② 红（有权限却拿到掩码）；
 *  - 从 route.php 的 /admin/v1 组摘掉 `OperationLog::class` ⇒ ① 与 ④ 红（结构钉子报缺环 + 日志数到 0 行）；
 *  - 删掉 reveal() 里的 `$request->setPost('target_user_id', $id);`（裸 ID 不进日志）⇒ ④ 红，
 *    报文 `Failed asserting that null is identical to 381306502155210752` —— 「只记 hashid」的写法咬得住；
 *  - ③ 的授权集合加上 reveal 权限 ⇒ ③ 红 —— 反向证明那个 403 出自「缺这条权限」，
 *    而不是链本身坏了（链坏了的假绿由 ② 挡着：同一条链在授权时是通的）；
 *  - 把 detail 的 maskUserContact 去掉（默认路径不脱敏）⇒
 *    PlatformUserPiiMaskingTest::detailMasksContactFieldsAndLoginIp 红（明文 13812345678 直接漏进响应）。
 */
final class PlatformUserRevealTest extends TestCase
{
    private const PHONE = '13812345678';
    private const EMAIL = 'reveal-probe@example.com';
    private const IP    = '203.0.113.45';

    /** 新权限 slug（由 AdminPermission 的归一算法实算：丢 v1 版本段 + 丢 {hashid} 占位段） */
    private const REVEAL_PERMISSION = 'post.admin/platform/user/reveal';
    /** 既有的「查看平台用户」权限 —— 用来证明 reveal **不是**复用它的 */
    private const VIEW_PERMISSION = 'get.admin/platform/user';

    private ?int $userId  = null;
    private ?int $adminId = null;

    /** @var string[] tearDown 要清的 Redis 键 */
    private array $redisKeys = [];

    protected function tearDown(): void
    {
        try {
            if ($this->adminId !== null) {
                OperationLogModel::where('user_id', $this->adminId)->delete();
                AdminUser::withTrashed()->where('id', $this->adminId)->forceDelete();
            }
            if ($this->userId !== null) {
                User::withTrashed()->where('id', $this->userId)->forceDelete();
            }
        } catch (Throwable) {
            // 库不可用时无需清理
        }
        foreach (array_unique($this->redisKeys) as $key) {
            try {
                Redis::del($key);
            } catch (Throwable) {
            }
        }
        $this->userId = $this->adminId = null;
        $this->redisKeys = [];
    }

    // ============================================================
    // ① 形态：POST 动作端点，不是可被 GET 触发的详情参数
    // ============================================================

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function revealRouteIsPostOnlyAndInheritsTheThreeLayerChain(): void
    {
        Route::load([__DIR__ . '/../config']);

        $info = Route::dispatch('POST', '/admin/v1/platform/user/AbCd1234/reveal');
        $this->assertSame(Dispatcher::FOUND, $info[0], 'POST /admin/v1/platform/user/{hashid}/reveal 未命中路由');
        $this->assertSame([PlatformUserController::class, 'reveal'], $info[1]['callback']);

        $middlewares = $info[1]['route']->getMiddleware();
        foreach ([
            AdminAuth::class,
            AdminPermission::class,
            OperationLog::class,
        ] as $middleware) {
            $this->assertContains(
                $middleware,
                $middlewares,
                "reveal 端点缺 {$middleware}：AdminAuth 管身份、AdminPermission 管独立权限、OperationLog 管留痕（这正是它必须是 POST 的原因）"
            );
        }

        // 形态判据：GET 到不了控制器 —— 同一 URI 只注册了 POST，FastRoute 回 METHOD_NOT_ALLOWED（=2），
        // 由框架转成 405（纯文本，非本仓的 200 信封），**不是** FOUND ⇒ 中间件链根本不执行。
        // 这正是"GET 形态记不到痕"的结构性原因：GET 在 OperationLog 里也会被早返回，两道都堵死。
        $this->assertSame(
            Dispatcher::METHOD_NOT_ALLOWED,
            Route::dispatch('GET', '/admin/v1/platform/user/AbCd1234/reveal')[0],
            'reveal 不得被 GET 触发：GET 会绕过留痕（OperationLog 对非 POST/PUT/DELETE 早返回）'
        );

        // 反向：新路由没顶掉详情路由，详情仍是 GET（它的脱敏钉子见 PlatformUserPiiMaskingTest）
        $detail = Route::dispatch('GET', '/admin/v1/platform/user/AbCd1234');
        $this->assertSame(Dispatcher::FOUND, $detail[0], '详情路由被 reveal 路由顶掉了');
        $this->assertSame([PlatformUserController::class, 'detail'], $detail[1]['callback']);
    }

    // ============================================================
    // ② + ④ 有权限：拿明文，且当场留下审计行
    // ============================================================

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function revealWithPermissionReturnsPlaintextAndWritesAnAuditRow(): void
    {
        $this->requireDatabase();
        $this->requireRedis();
        $this->createProbeUser();
        $admin = $this->createProbeAdmin();
        $this->warmPermissionCache($admin, [self::REVEAL_PERMISSION]);
        $hashid = HashidsService::encode((int) $this->userId);

        // 留痕读数必须是「这条请求产生的」：按本探针管理员计行，前后必须差 1（别人写的不算数）
        $this->assertSame([], $this->auditRows($admin), '探针管理员在请求前不该有审计行');

        [$reached, $request, $response] = $this->runThroughRealChain($admin, $hashid);

        $this->assertSame($admin, $request->adminId, 'AdminAuth 必须已解出身份（否则下面拦下的可能是"没登录"而不是"没权限"）');
        $this->assertTrue($reached, '持 post.admin/platform/user/reveal 必须放行到控制器');

        $body = json_decode($response->rawBody(), true);
        $this->assertSame(0, $body['code'] ?? null, '放行的响应应是成功信封，实际：' . $response->rawBody());
        $this->assertSame(self::PHONE, $body['data']['phone'], '有权限时必须拿到完整手机号，而不是掩码');
        $this->assertSame(self::EMAIL, $body['data']['email'], '有权限时必须拿到完整邮箱');
        $this->assertSame(self::IP, $body['data']['last_login_ip'], '有权限时必须拿到完整登录 IP');

        // ---- ④ 留痕 ----
        $rows = $this->auditRows($admin);
        $this->assertCount(1, $rows, '这次 reveal 必须且只落一行操作日志（0 行 = 白看了，多行 = 记重了）');
        $row = $rows[0];
        $this->assertSame($admin, (int) $row->user_id, '日志必须记到**操作者**');
        $this->assertStringContainsString($hashid, (string) $row->path, '日志必须记到**目标**（path 里的 hashid）');
        $this->assertSame('POST', (string) $row->method);
        $this->assertNotSame('', (string) $row->created_at, '日志必须有时间戳');

        // 目标的**裸 ID** 也必须在日志里：path 只有 hashid，而 hashid 反查依赖 HASHIDS_SALT 稳定
        // ⇒ salt 一轮换，历史行的目标列就静默不可解。操作者那列本来就是裸 BIGINT，两列必须对称。
        $input = json_decode((string) $row->input, true);
        $this->assertSame(
            (int) $this->userId,
            $input['target_user_id'] ?? null,
            '日志必须记到目标的裸 ID（target_user_id）—— 只有 hashid 的话审计追溯绑死在 salt 上'
        );

        // 反向：裸 ID **只进日志、不进响应**。响应侧仍只用 hashid 防 IDOR/枚举，两条诉求不是一回事。
        $this->assertStringNotContainsString(
            (string) $this->userId,
            $response->rawBody(),
            '裸 ID 泄漏进响应了：它只该出现在操作日志里'
        );
    }

    // ============================================================
    // ③ 无 reveal 权限：被拒，且不留行
    // ============================================================

    /**
     * 只有「查看平台用户」权限（能看脱敏详情）时必须 403。
     *
     * 这条同时是「独立权限点」的**唯一**证明面：把种子行/reveal 的 slug 改成复用
     * `get.admin/platform/user`，本用例立刻红 —— 授予了看脱敏详情不等于授予了看全号。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function revealWithoutTheRevealPermissionIsRejectedAndWritesNoAuditRow(): void
    {
        $this->requireDatabase();
        $this->requireRedis();
        $this->createProbeUser();
        $admin = $this->createProbeAdmin();
        $this->warmPermissionCache($admin, [self::VIEW_PERMISSION]);
        $hashid = HashidsService::encode((int) $this->userId);

        [$reached, $request, $response] = $this->runThroughRealChain($admin, $hashid);

        $this->assertSame($admin, $request->adminId, '身份必须已解出 —— 否则拦下的是"没登录"，不是"没权限"');
        $this->assertFalse($reached, '只有 get.admin/platform/user 时不得放行：能看脱敏详情 ≠ 能看全号');

        // 本仓 RBAC 的既有拒绝形态：HTTP 200 + JSON 信封，语义在信封的 code 里
        $this->assertSame(200, $response->getStatusCode(), '中间件拒绝是 HTTP 200（全仓约定，未随之改变）');
        $body = json_decode($response->rawBody(), true);
        $this->assertSame(403, $body['code'] ?? null, '信封 code 必须是 403，实际：' . $response->rawBody());
        $this->assertStringNotContainsString(self::PHONE, $response->rawBody(), '被拒的响应里不得出现全号');

        $this->assertSame([], $this->auditRows($admin), '被拒的请求没有 reveal 动作可记（OperationLog 排在 AdminPermission 之后，未执行）');
    }

    // ============================================================
    // 辅助
    // ============================================================

    /**
     * 真请求穿**该路由自己挂的那条链**：AdminAuth → AdminPermission → OperationLog → 控制器。
     *
     * 链不是在测试里抄的清单：它取自框架自己的 `Middleware::getMiddleware()` —— 与生产上
     * `App::getCallback()` 用的是同一个函数；有人把 OperationLog 从路由/组上摘掉，这里立刻拿不到那一环。
     * `$withGlobalMiddleware = false` 只关掉全局段（Cors/SecurityFilter/RateLimit/Language）：
     * 那四个与被测的三层无关，且 RateLimit 要走 Redis 计数，会在断言前先把请求拒掉。
     *
     * 装配方向照框架的 `array_reduce`（App.php 里同款）：返回数组是**建链序**，
     * 最后一个元素最先执行 —— 折错方向时 `$request->adminId` 还没注入，本方法的调用方会当场红。
     *
     * @return array{0: bool, 1: Request, 2: Response} [是否到达控制器, 请求对象, 响应]
     */
    private function runThroughRealChain(int $adminId, string $targetHashid): array
    {
        Route::load([__DIR__ . '/../config']);

        $uri  = sprintf('/admin/v1/platform/user/%s/reveal', $targetHashid);
        $info = Route::dispatch('POST', $uri);
        $this->assertSame(Dispatcher::FOUND, $info[0], "POST {$uri} 未命中路由");

        $request = new Request(
            "POST {$uri} HTTP/1.1\r\nHost: localhost\r\n"
            . 'Authorization: Bearer ' . $this->mintJwt($adminId) . "\r\n\r\n"
        );
        $request->route = $info[1]['route'];

        $reached = false;
        $innermost = function (Request $req) use (&$reached, $targetHashid): Response {
            $reached = true;

            return (new PlatformUserController())->reveal($req, $targetHashid);
        };

        $chain    = Middleware::getMiddleware('', '', $info[1]['callback'], $info[1]['route'], false);
        $callback = array_reduce($chain, static function (callable $carry, array $pipe): callable {
            [$class, $method] = $pipe;

            return static fn(Request $req): Response => (new $class())->{$method}($req, $carry);
        }, $innermost);

        // 必须先执行、再读 $reached：数组元素从左往右求值，写成 [$reached, $request, $callback(...)]
        // 会在链跑起来之前就读走 $reached（恒 false）。
        $response = $callback($request);

        return [$reached, $request, $response];
    }

    /** 该管理员在 game_operation_log 里的行（探针管理员 id 唯一 ⇒ 不会被别的用例污染） */
    private function auditRows(int $adminId): array
    {
        return OperationLogModel::where('user_id', $adminId)->orderBy('created_at')->get()->all();
    }

    private function mintJwt(int $adminId): string
    {
        return jwt_instance()->encode(['sub' => $adminId, 'username' => 'reveal_probe']);
    }

    private function warmPermissionCache(int $adminId, array $permissions): void
    {
        // 必须先有 status=1 的管理员行：AdminPermission 读缓存**之前**要查库拦停用账号（fail-closed）
        Redis::setex("perm:{$adminId}", 60, json_encode($permissions));
        $this->redisKeys[] = "perm:{$adminId}";
    }

    private function createProbeUser(): void
    {
        $user = new User();
        $user->id            = SnowflakeService::generate();
        $user->username      = 'revealprobe_' . bin2hex(random_bytes(5));
        $user->password      = password_hash('Probe1234', PASSWORD_BCRYPT);
        $user->nickname      = 'reveal_probe';
        $user->country       = 'CN';
        $user->email         = self::EMAIL;   // Encryptable：库里落密文，模型取回明文
        $user->phone         = self::PHONE;
        $user->last_login_ip = self::IP;
        $user->status        = 1;
        $user->save();
        $this->userId = (int) $user->id;
    }

    private function createProbeAdmin(): int
    {
        $user = new AdminUser();
        $user->id       = SnowflakeService::generate();
        $user->username = 'reveal_probe_' . bin2hex(random_bytes(6));
        $user->password = password_hash('Probe1234', PASSWORD_BCRYPT);
        $user->real_name = 'reveal_probe';
        $user->email    = '';
        $user->phone    = '';
        $user->status   = 1;
        $user->save();
        $this->adminId = (int) $user->id;

        return $this->adminId;
    }

    private function requireDatabase(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }
    }

    private function requireRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
    }
}
