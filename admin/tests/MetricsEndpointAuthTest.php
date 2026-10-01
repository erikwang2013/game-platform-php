<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\MetricsController;
use app\middleware\AdminAuth;
use app\middleware\AdminPermission;
use app\middleware\MetricsAuth;
use app\model\AdminUser;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Redis;
use support\Request;
use support\Response;
use Webman\Route;

/**
 * `/metrics` 认证与拒绝形状的钉子。
 *
 * 背景：该端点原来只认管理员 JWT（会过期，抓取器没法长期用），且拒绝沿用了全仓的
 * 「HTTP 200 + JSON 信封」约定 —— 只判状态码的抓取器会把 401 当成功、再在解析正文时报格式错，
 * 判 Content-Type 的会把 target 判成 down。现改为 MetricsAuth：管理员 JWT（仍走 RBAC）
 * 或配置里的静态抓取令牌；拒绝一律 401/403 + text/plain。
 *
 * 本文件钉四件事：
 *  1. 静态令牌路径 **fail-closed**（令牌未配置时任何输入都不得通过）—— 最重要的一条；
 *  2. 管理员 JWT 那条路**没被弄坏**：有权限照常放行，没权限仍被 AdminPermission 拦住；
 *  3. 拒绝形状是抓取器读得懂的 401/403 + text/plain（不是 200 + JSON）；
 *  4. 爆炸半径 = 1 条路由：其它端点的中间件与拒绝形状原样不动。
 */
class MetricsEndpointAuthTest extends TestCase
{
    /** 测试用静态令牌；真值来自 config/app.php，绝不出现在生产代码里 */
    private const SCRAPE_TOKEN = 'probe-scrape-token-0123456789abcdef';

    /** @var string[] 用例创建的管理员用户名，tearDown 据此清库 */
    private array $probeUsernames = [];

    /** @var string[] tearDown 要清的 Redis 键 */
    private array $redisKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->probeUsernames as $username) {
            try {
                $user = AdminUser::withTrashed()->where('username', $username)->first();
                if ($user) {
                    $this->redisKeys[] = "perm:{$user->id}";
                    $user->forceDelete();
                }
            } catch (\Throwable) {
                // 库不可用时无需清理
            }
        }
        foreach (array_unique($this->redisKeys) as $key) {
            try {
                Redis::del($key);
            } catch (\Throwable) {
            }
        }
        $this->probeUsernames = [];
        $this->redisKeys    = [];

        parent::tearDown();
    }

    // ============================================================
    // 1. 静态令牌路径
    // ============================================================

    /** 有效静态令牌 ⇒ 直接放行到控制器，且正文是真 Prometheus 文本 */
    #[Test]
    public function validScrapeTokenReachesTheController(): void
    {
        $reached = false;
        $response = $this->middlewareConfiguredWith(self::SCRAPE_TOKEN)->process(
            $this->request('Bearer ' . self::SCRAPE_TOKEN),
            function (Request $request) use (&$reached): Response {
                $reached = true;
                return (new MetricsController())->index($request);
            }
        );

        $this->assertTrue($reached, '持有有效静态令牌必须放行到控制器');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', (string) $response->getHeader('Content-Type'));

        $body = $response->rawBody();
        $this->assertStringContainsString('# HELP open_admin_info', $body, '正文必须是 Prometheus 格式，而不是错误信封');
        $this->assertStringContainsString('open_admin_info{version=', $body);
    }

    /** 令牌配置为空 ⇒ 静态路径永不匹配（fail-closed）—— 本文件最重要的一条 */
    #[Test]
    public function emptyConfiguredTokenNeverMatches(): void
    {
        // 逐条都是"看起来能让 hash_equals('','') 变真"的输入；空配置下必须全部 401
        foreach ([null, '', 'Bearer', 'Bearer ', 'Bearer ' . self::SCRAPE_TOKEN] as $authorization) {
            $reached  = false;
            $response = $this->middlewareConfiguredWith('')->process(
                $this->request($authorization),
                function () use (&$reached): Response {
                    $reached = true;
                    return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
                }
            );

            $this->assertFalse($reached, '令牌未配置时不得放行（Authorization: ' . var_export($authorization, true) . '）');
            $this->assertSame(
                401,
                $response->getStatusCode(),
                '令牌未配置 ⇒ 静态路径必须永不匹配；空配置能过就是「空令牌可进」的开洞（hash_equals(\'\', \'\') 恒真）'
            );
        }
    }

    /** 令牌配置了，但送来的不对 ⇒ 401 */
    #[Test]
    public function wrongScrapeTokenIsRejected(): void
    {
        $reached  = false;
        $response = $this->middlewareConfiguredWith(self::SCRAPE_TOKEN)->process(
            $this->request('Bearer not-the-right-token'),
            function () use (&$reached): Response {
                $reached = true;
                return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
            }
        );

        $this->assertFalse($reached, '令牌不匹配不得放行');
        $this->assertPlainTextRejection($response, 401);
    }

    /** 完全不带凭据 ⇒ 401 + text/plain（不是 200 + JSON 信封） */
    #[Test]
    public function missingTokenIsRejectedWithPlainText401(): void
    {
        $reached  = false;
        $response = (new MetricsAuth())->process(
            $this->request(null),
            function () use (&$reached): Response {
                $reached = true;
                return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
            }
        );

        $this->assertFalse($reached);
        $body = $this->assertPlainTextRejection($response, 401);
        $this->assertStringContainsString('metrics scrape token', $body, '拒绝行要说明该带什么凭据，否则运维只能去翻源码');
    }

    /** 无效 Bearer（不是 JWT 也不是静态令牌）⇒ 401，且形状同样是 text/plain */
    #[Test]
    public function invalidBearerTokenIsRejected(): void
    {
        $reached  = false;
        $response = (new MetricsAuth())->process(
            $this->request('Bearer garbage.not.a.jwt'),
            function () use (&$reached): Response {
                $reached = true;
                return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
            }
        );

        $this->assertFalse($reached);
        $this->assertPlainTextRejection($response, 401);
    }

    // ============================================================
    // 2. 管理员 JWT 路径（原路径不得被弄坏）
    // ============================================================

    /** 有权限的管理员 JWT ⇒ 照常放行到控制器（证明静态令牌没把原路径替换掉） */
    #[Test]
    public function adminJwtWithPermissionStillPasses(): void
    {
        $this->requireRedis();
        $user   = $this->createAdmin(1);
        $adminId = (int) $user->id;
        $this->warmPermissionCache($adminId, ['*']);

        $reached  = false;
        $response = (new MetricsAuth())->process(
            $this->request('Bearer ' . $this->mintJwt($adminId)),
            function (Request $request) use (&$reached): Response {
                $reached = true;
                return (new MetricsController())->index($request);
            }
        );

        $this->assertTrue($reached, '持有效管理员 JWT 必须照常放行 —— 加静态令牌不得把原路径弄坏');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('# HELP open_admin_info', $response->rawBody());
    }

    /**
     * 管理员 JWT 有效但**没有** get.metrics 权限 ⇒ 仍被 AdminPermission 拦下（RBAC 没被摘掉）。
     *
     * ⚠ 这条同时是 `PermissionSeedParityTest` 那处**具名豁免**的兜底钉子：那条把取集判据放宽成
     * 「挂了 AdminPermission **或** MetricsAuth」，等于把「MetricsAuth 会执行 RBAC」降级成一个**假设**。
     * 删掉 MetricsAuth 里对 AdminPermission 的委托会让这条红 —— 动那个委托前先跑这条。
     */
    #[Test]
    public function adminJwtWithoutMetricsPermissionIsStillRejectedByRbac(): void
    {
        $this->requireRedis();
        $user    = $this->createAdmin(1);
        $adminId = (int) $user->id;
        $this->warmPermissionCache($adminId, ['get.admin/other']);

        $request  = $this->request('Bearer ' . $this->mintJwt($adminId));
        $reached  = false;
        $response = (new MetricsAuth())->process(
            $request,
            function () use (&$reached): Response {
                $reached = true;
                return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
            }
        );

        $this->assertSame($adminId, $request->adminId, 'JWT 必须仍被 AdminAuth 解出身份（否则下面拦下的可能是"没登录"而不是"没权限"）');
        $this->assertFalse($reached, '授权检查不得为了"让令牌好走"而被整个摘掉');
        $body = $this->assertPlainTextRejection($response, 403);
        $this->assertStringContainsString('get.metrics', $body);
    }

    /** 签得出但没有 sub 的令牌 ⇒ 401 而不是 403：没有身份就是"没登录"，报"没权限"会把运维引偏 */
    #[Test]
    public function signedJwtWithoutSubjectIsRejectedAsUnauthenticated(): void
    {
        $reached  = false;
        $response = (new MetricsAuth())->process(
            $this->request('Bearer ' . jwt_instance()->encode(['username' => 'metrics_probe'])),
            function () use (&$reached): Response {
                $reached = true;
                return response('open_admin_info 1', 200, ['Content-Type' => 'text/plain']);
            }
        );

        $this->assertFalse($reached);
        $this->assertPlainTextRejection($response, 401);
    }

    // ============================================================
    // 3. 爆炸半径：只有 /metrics 一条路由被改
    // ============================================================

    /**
     * 路由表口径：挂 MetricsAuth 的路由**有且只有** /metrics；其它端点的中间件原样不动。
     *
     * 两个方向各钉一个改动（本仓没有「跳过单个中间件」的机制，MetricsAuth 对 RBAC 的委托在
     * `getMiddleware()` 里看不见，所以这条路由表钉子只能钉住"挂在哪"，"还委不委托"由
     * {@see adminJwtWithoutMetricsPermissionIsStillRejectedByRbac} 用行为钉）：
     *  - 把 MetricsAuth 挂到**别的路由** ⇒ `assertSame(['/metrics'], …)` 红（M8 实测）；
     *  - 把 /metrics 换回 AdminAuth（即撤掉 MetricsAuth）⇒ 同一条断言红（M7 实测）。
     * 这两条一起构成 `PermissionSeedParityTest` 那处具名豁免的兜底之一。
     *
     * 必须 RunInSeparateProcess：Route::load() 每次调用先清空 dispatcher/allRoutes 等静态状态，
     * 同进程里第二次调用拿回 0 条路由，会与 GameRouteTest / PermissionSeedParityTest 互相打成假红。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function onlyTheMetricsRouteCarriesMetricsAuth(): void
    {
        Route::load([__DIR__ . '/../config']);

        $routes = Route::getRoutes();
        $this->assertNotEmpty($routes, '路由未装载：Route::load() 未生效');

        $withMetricsAuth = [];
        $byPath          = [];
        foreach ($routes as $route) {
            $middleware = $route->getMiddleware();
            $byPath[$route->getPath()] = $middleware;
            if (in_array(MetricsAuth::class, $middleware, true)) {
                $withMetricsAuth[] = $route->getPath();
            }
        }

        $this->assertSame(['/metrics'], array_values($withMetricsAuth), 'MetricsAuth 只允许挂在 /metrics 这一条路由上');
        $this->assertSame([MetricsAuth::class], $byPath['/metrics'], '/metrics 应只挂 MetricsAuth（RBAC 由它内部对 JWT 路径委托）');

        // ⚠ getMiddleware() 返回的是**建链序**，不是声明序：Route::middleware() 存的是 array_reverse(声明)，
        // 装配时 ()再反一次才还原成声明序执行（AdminAuth → AdminPermission）。所以这里只比集合、不比顺序，
        // 免得把「谁反了谁」这种框架内部细节钉成"预期"。
        $this->assertEqualsCanonicalizing(
            [AdminAuth::class, AdminPermission::class],
            $byPath['/api/docs'],
            '/api/docs 的认证不得被本次改动波及'
        );
        $this->assertSame([], $byPath['/health'], '/health 保持公开');
    }

    /** 其它端点的拒绝形状仍是全仓约定：HTTP 200 + JSON 信封 */
    #[Test]
    public function otherEndpointsKeepTheJsonEnvelope(): void
    {
        $request  = new Request("GET /admin/v1/dashboard HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $reached  = false;
        $response = (new AdminAuth())->process($request, function () use (&$reached): Response {
            $reached = true;
            return response('passed', 200);
        });

        $this->assertFalse($reached, '无令牌必须被拒');
        $this->assertSame(200, $response->getStatusCode(), '中间件拒绝＝HTTP 200（全仓约定，未随 /metrics 一起改）');
        $this->assertStringStartsWith('application/json', (string) $response->getHeader('Content-Type'));

        $body = json_decode($response->rawBody(), true);
        $this->assertIsArray($body);
        $this->assertSame(401, $body['code'], '拒绝语义在信封的 code 里，不在 HTTP 状态码里');
    }

    // ============================================================
    // 4. 令牌来源：配置 + 环境变量，不硬编码
    // ============================================================

    /** 令牌真值来自环境变量（行为级，不是"源码里出现过 getenv"这种文本判据） */
    #[Test]
    public function scrapeTokenIsReadFromConfigAndEnv(): void
    {
        $saved = getenv('METRICS_SCRAPE_TOKEN');
        try {
            putenv('METRICS_SCRAPE_TOKEN=probe-value-xyz');
            $this->assertSame(
                'probe-value-xyz',
                (require __DIR__ . '/../config/app.php')['metrics_scrape_token'],
                '环境变量必须真的被读到 —— 硬编码一个字面量会让这条红'
            );
        } finally {
            $saved === false ? putenv('METRICS_SCRAPE_TOKEN') : putenv("METRICS_SCRAPE_TOKEN={$saved}");
        }

        putenv('METRICS_SCRAPE_TOKEN');
        try {
            $this->assertSame(
                '',
                (require __DIR__ . '/../config/app.php')['metrics_scrape_token'],
                '未设置时必须回落到空串（静态令牌路径随之关闭）；不得有非空默认值'
            );
        } finally {
            $saved === false ? putenv('METRICS_SCRAPE_TOKEN') : putenv("METRICS_SCRAPE_TOKEN={$saved}");
        }

        $token = (new ReflectionMethod(MetricsAuth::class, 'configuredToken'))->invoke(new MetricsAuth());
        $this->assertSame(
            config('app.metrics_scrape_token'),
            $token,
            'configuredToken() 必须取自 config，覆写点不能让"配置接线断了"这件事在测试里隐形'
        );
    }

    // ============================================================
    // 辅助
    // ============================================================

    /** 注入固定令牌的 MetricsAuth（覆写 protected 的取配置入口，同 AdminPermission::getUserPermissions 的做法） */
    private function middlewareConfiguredWith(string $token): MetricsAuth
    {
        return new class ($token) extends MetricsAuth {
            public function __construct(private readonly string $token)
            {
            }

            protected function configuredToken(): string
            {
                return $this->token;
            }
        };
    }

    /** $Authorization 为 null 表示完全不带该头 */
    private function request(?string $authorization): Request
    {
        $header = $authorization === null ? '' : "Authorization: {$authorization}\r\n";

        return new Request("GET /metrics HTTP/1.1\r\nHost: localhost\r\n{$header}\r\n");
    }

    /** 断言"抓取器读得懂"的拒绝形状，返回正文供进一步断言 */
    private function assertPlainTextRejection(Response $response, int $status): string
    {
        $this->assertSame($status, $response->getStatusCode(), 'HTTP 状态码必须是真实错误码（不能是 200）');
        $this->assertStringStartsWith(
            'text/plain',
            (string) $response->getHeader('Content-Type'),
            '拒绝必须是 text/plain：Prometheus 只在 2xx 时读正文，判 Content-Type 的抓取器要靠它区分'
        );

        $body = $response->rawBody();
        $this->assertNotSame('', trim($body), '拒绝正文不能为空');
        $this->assertNull(json_decode($body, true), '拒绝正文必须是纯文本单行，不能是 JSON 信封');
        $this->assertStringStartsWith((string) $status, $body, '首行应以上下文可读的错误码开头');

        return $body;
    }

    private function mintJwt(int $adminId): string
    {
        return jwt_instance()->encode(['sub' => $adminId, 'username' => 'metrics_probe']);
    }

    private function createAdmin(int $status): AdminUser
    {
        $username = 'metrics_probe_' . bin2hex(random_bytes(6));
        $this->probeUsernames[] = $username;

        try {
            $user = new AdminUser();
            $user->id = SnowflakeService::generate();
            $user->username = $username;
            $user->password = password_hash('Probe1234', PASSWORD_BCRYPT);
            $user->real_name = 'metrics_probe';
            $user->email = '';
            $user->phone = '';
            $user->status = $status;
            $user->save();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->redisKeys[] = "perm:{$user->id}";

        return $user;
    }

    private function warmPermissionCache(int $adminId, array $permissions): void
    {
        Redis::setex("perm:{$adminId}", 60, json_encode($permissions));
    }

    private function requireRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
    }
}
