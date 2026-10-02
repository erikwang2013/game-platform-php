<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\AdminPermission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route\Route as RouteObject;

/**
 * AdminPermission 的 slug 归一 —— 成对用例
 *
 * 背景：旧实现用 `$request->path()`（真实请求路径）拼 slug，带占位符的路由因此永远算不出
 * 可授权的 slug（PUT /admin/v1/user/{id} 的请求路径是 /admin/v1/user/AbC123xY ⇒
 * slug `put.admin/user/AbC123xY`，install.sql 里没有、也不可能有这样一行）。
 * 归一为「路由模式 + 丢占位符段」后 PUT /admin/v1/user/{id} → `put.admin/user`。
 *
 * 这个方向是本批唯一的"开洞级"改动，所以用例必须成对：
 *   ① 允许路径：非 `*` 角色拿得到**它被授予的**权限；
 *   ② 守禁路径：它拿不到**没授予的** —— 占位符路由不得因为通配/前缀/换方法而放开。
 * 只有 ① 会让"验证"变成开洞，② 才是这次改动真正的验收面。
 *
 * 本类**不调用 Route::load**：它内部是 `require_once`，同进程第二次调用只会把静态路由表
 * 清成 0 条（实测 188 → 0），而 tests/GameRouteTest.php 正是靠它装载真实路由表的 ——
 * 本类若先跑（类名字母序在前）会把那张表清空，让 GameRouteTest 假红。
 * 所以这里直接构造 RouteObject，模式串逐字抄自 config/route.php（见下方注释与
 * pinsRouteTableShapeStillMatches 用例）。
 */
class AdminPermissionMatchingTest extends TestCase
{
    /**
     * 走真实中间件入口，只把权限查询换成固定集合。
     *
     * 判据取**信封里的 code**而不是 HTTP 状态码：中间件的拒绝是 `json(['code'=>403…])`，
     * 而 webman 的 json() 恒返回 HTTP 200（helpers.php:185），把 code 放在 body 里。
     * 两条路径都产 JSON，所以这里统一读 code，不存在"200 也可能是拒绝"的歧义。
     *
     * @param array  $granted 该管理员被授予的 slug
     * @param string $method  HTTP 方法
     * @param string $pattern 路由模式（config/route.php 的注册原文）
     * @param string $realPath 请求真实路径（故意带实体 id，证明 slug 与它无关）
     * @return int 信封 code：200 放行 / 403 拒绝
     */
    private function gate(array $granted, string $method, string $pattern, string $realPath): int
    {
        $middleware = new class($granted) extends AdminPermission {
            public function __construct(private readonly array $granted) {}

            protected function getUserPermissions(int $adminId): array
            {
                return $this->granted;
            }
        };

        $request = new Request("{$method} {$realPath} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->route = new RouteObject([$method], $pattern, static fn() => null);
        $request->adminId = 1;

        $next = static fn() => json(['code' => 200, 'message' => 'passed']);
        $body = json_decode($middleware->process($request, $next)->rawBody(), true);

        return (int) ($body['code'] ?? 0);
    }

    /** 反射纯函数，直接看归一后的 slug */
    private function slugFor(string $method, string $pattern): string
    {
        $middleware = new AdminPermission();
        $permissionPath = new ReflectionMethod(AdminPermission::class, 'permissionPath');
        $stripVersion = new ReflectionMethod(AdminPermission::class, 'stripVersionSegment');

        $request = new Request("{$method} /irrelevant HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->route = new RouteObject([$method], $pattern, static fn() => null);

        $path = $stripVersion->invoke($middleware, $permissionPath->invoke($middleware, $request));

        return strtolower($method) . '.' . trim($path, '/');
    }

    // ============================================================ ① 允许路径

    /**
     * @return array<string, array{array, string, string, string}>
     */
    public static function grantedCases(): array
    {
        return [
            // config/route.php:78 Route::resource('/user', UserController::class) ⇒ PUT /user/{id}
            'put.admin/user 改用户'    => [['put.admin/user'], 'PUT', '/admin/v1/user/{id}', '/admin/v1/user/AbC123xY'],
            // config/route.php:83 Route::resource('/role', ...) ⇒ DELETE /role/{id}
            'delete.admin/role 删角色' => [['delete.admin/role'], 'DELETE', '/admin/v1/role/{hashid}', '/admin/v1/role/Zz9'],
            // config/route.php:86 Route::resource('/permission', ...) ⇒ PUT /permission/{id}
            'put.admin/permission'     => [['put.admin/permission'], 'PUT', '/admin/v1/permission/{id}', '/admin/v1/permission/7'],
            // config/route.php:91 Route::put('/config/{id}', ...)
            'put.admin/config 改配置'  => [['put.admin/config'], 'PUT', '/admin/v1/config/{id}', '/admin/v1/config/42'],
            // 静态路由：本批次前后都该放行（归一不得打坏既有授权）
            'get.admin/user 列用户'    => [['get.admin/user'], 'GET', '/admin/v1/user', '/admin/v1/user'],
            'get.admin/dashboard'      => [['get.admin/dashboard'], 'GET', '/admin/v1/dashboard', '/admin/v1/dashboard'],
            // 多权限里有一个命中即可
            '集合中命中其一'           => [['get.admin/log', 'put.admin/user', 'put.admin/system'], 'PUT', '/admin/v1/user/{id}', '/admin/v1/user/9'],
            // '*' 全权与此改动无关，但顺带钉住它没被打坏
            '全权星号'                 => [['*'], 'PUT', '/admin/v1/user/{id}', '/admin/v1/user/1'],
        ];
    }

    #[Test]
    #[DataProvider('grantedCases')]
    public function grantedRolePasses(array $granted, string $method, string $pattern, string $realPath): void
    {
        $this->assertSame(200, $this->gate($granted, $method, $pattern, $realPath));
    }

    // ============================================================ ② 守禁路径

    /**
     * @return array<string, array{array, string, string}>
     */
    public static function deniedCases(): array
    {
        return [
            // 授予了「改」不等于授予了「删」
            '有 put 无 delete'          => [['put.admin/user'], 'DELETE', '/admin/v1/user/{id}'],
            // 授予了「读」不等于授予了「写」
            '有 get 无 put'             => [['get.admin/user'], 'PUT', '/admin/v1/user/{id}'],
            '有 get 无 delete'          => [['get.admin/user'], 'DELETE', '/admin/v1/user/{id}'],
            // 同方法不同资源：config 的写权绝不能顺带打开 user 的写权
            'config 写权不覆盖 user'    => [['put.admin/config'], 'PUT', '/admin/v1/user/{id}'],
            'role 写权不覆盖 user'      => [['put.admin/role'], 'PUT', '/admin/v1/user/{id}'],
            'user 写权不覆盖 role'      => [['put.admin/user'], 'PUT', '/admin/v1/role/{hashid}'],
            'permission 写权不覆盖 user' => [['put.admin/permission'], 'PUT', '/admin/v1/user/{id}'],
            // 前缀/后缀/裸资源名都不许命中（in_array 是精确匹配，这里钉死没有退化成通配）
            '前缀 put.admin'            => [['put.admin'], 'PUT', '/admin/v1/user/{id}'],
            '后缀 admin/user'           => [['admin/user'], 'PUT', '/admin/v1/user/{id}'],
            '裸资源名 user'             => [['user'], 'PUT', '/admin/v1/user/{id}'],
            '带星号前缀 put.admin/*'    => [['put.admin/*'], 'PUT', '/admin/v1/user/{id}'],
            '方法大小写不符'            => [['PUT.admin/user'], 'PUT', '/admin/v1/user/{id}'],
            // 权限集为空 / 无关权限
            '空权限集'                  => [[], 'PUT', '/admin/v1/user/{id}'],
            '无关权限集'                => [['get.admin/log'], 'PUT', '/admin/v1/user/{id}'],
            '全权星号缺失的集合'        => [['admin.*', '*.*'], 'PUT', '/admin/v1/user/{id}'],
        ];
    }

    #[Test]
    #[DataProvider('deniedCases')]
    public function ungrantedRoleIsRejected(array $granted, string $method, string $pattern): void
    {
        // 同一组权限下，把真实路径换成另一个 id 也必须同样被拒（不能被 id 影响判定）
        foreach (['/admin/v1/user/AbC123xY', '/admin/v1/user/1'] as $realPath) {
            $this->assertSame(403, $this->gate($granted, $method, $pattern, $realPath), "{$method} {$pattern} 竟然放行了");
        }
    }

    /**
     * 占位符路由不得因为"归一"而对**任何**权限集全放开：逐个 slug 穷举，
     * 只有恰好等于该 slug 的那一个集合能过，其余一律 403。
     */
    #[Test]
    public function placeholderRouteOpensOnlyForItsOwnSlug(): void
    {
        $pattern = '/admin/v1/user/{id}';
        $required = $this->slugFor('PUT', $pattern);
        $this->assertSame('put.admin/user', $required);

        $this->assertSame(200, $this->gate([$required], 'PUT', $pattern, '/admin/v1/user/AbC'));
        $this->assertSame(403, $this->gate([], 'PUT', $pattern, '/admin/v1/user/AbC'));

        // 把仓储里可能出现的近邻 slug 全试一遍，一个都不许过
        foreach ([
            'put.admin/user/{id}', 'put.admin/user/*', 'put.admin', 'put', 'user',
            'delete.admin/user', 'get.admin/user', 'put.admin/users', 'put.admin/user/1',
        ] as $near) {
            $this->assertSame(403, $this->gate([$near], 'PUT', $pattern, '/admin/v1/user/AbC'), "近邻 slug {$near} 不该放行");
        }
    }

    // ============================================================ slug 归一本身

    #[Test]
    public function slugDropsPlaceholderSegments(): void
    {
        $this->assertSame('put.admin/user', $this->slugFor('PUT', '/admin/v1/user/{id}'));
        $this->assertSame('delete.admin/leaderboard', $this->slugFor('DELETE', '/admin/v1/leaderboard/{hashid}'));
        // 中间的占位符段同样要丢
        $this->assertSame('post.admin/leaderboard/refresh', $this->slugFor('POST', '/admin/v1/leaderboard/{hashid}/refresh'));
        $this->assertSame('get.admin/risk/users/timeline', $this->slugFor('GET', '/admin/v1/risk/users/{hashid}/timeline'));
    }

    #[Test]
    public function slugIsIndependentOfEntityId(): void
    {
        $middleware = new AdminPermission();
        $method = new ReflectionMethod(AdminPermission::class, 'permissionPath');

        $slugs = [];
        foreach (['AbC123', 'ZZZZZZZZZZZZZZZZZZ', '1', 'a-b_c'] as $id) {
            $request = new Request("PUT /admin/v1/user/{$id} HTTP/1.1\r\nHost: localhost\r\n\r\n");
            $request->route = new RouteObject(['PUT'], '/admin/v1/user/{id}', static fn() => null);
            $slugs[] = $method->invoke($middleware, $request);
        }

        // 旧实现下这四个会得到四个不同 slug（也就是四个不同权限），新实现恒为同一个
        $this->assertSame(['/admin/v1/user'], array_values(array_unique($slugs)));
        $this->assertCount(4, $slugs);
    }

    /** 旧实现（HEAD 的 process + stripVersionSegment）逐字搬过来的 slug 算法 */
    private function legacySlug(string $method, string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1 && preg_match('/^v\d+$/', $segments[1])) {
            unset($segments[1]);
        }

        return strtolower($method) . '.' . trim('/' . implode('/', $segments), '/');
    }

    #[Test]
    public function staticPathsStayByteIdentical(): void
    {
        // 静态路由：归一前（真实路径）与归一后（模式）必须逐字节相同，否则既有授权面会漂移。
        // 全量 107/107 的证明在 /tmp 的装载真实路由表的脚本里；这里钉规则本身。
        foreach ([
            ['GET', '/admin/v1/dashboard'],
            ['GET', '/admin/v1/user'],
            ['POST', '/admin/v1/user'],
            ['POST', '/admin/v1/user/batch/status'],
            ['PUT', '/admin/v1/profile/password'],
            ['POST', '/admin/v1/export/users'],
            ['GET', '/admin/v1/risk/overview'],
            ['POST', '/admin/v1/withdraw/batch-review'],
            ['GET', '/metrics'],          // 不带版本段
            ['GET', '/admin/v1/withdraw/switch'],
        ] as [$method, $path]) {
            $this->assertSame($this->legacySlug($method, $path), $this->slugFor($method, $path), "{$method} {$path} 归一前后 slug 变了");
        }
    }

    #[Test]
    public function versionSegmentIsStillStripped(): void
    {
        $this->assertSame('get.admin/user', $this->slugFor('GET', '/admin/v1/user'));
        // 无版本段的模式不受影响
        $this->assertSame('get.metrics', $this->slugFor('GET', '/metrics'));
    }

    #[Test]
    public function missingRouteFallsBackToRealPath(): void
    {
        // $request->route 为 null（未命中路由）时退回旧行为，不得因归一而放行或崩溃
        $middleware = new AdminPermission();
        $method = new ReflectionMethod(AdminPermission::class, 'permissionPath');

        $request = new Request("GET /admin/v1/whatever HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $this->assertSame('/admin/v1/whatever', $method->invoke($middleware, $request));
    }

    // ============================================================ 与 route.php 的对齐

    /**
     * 本类直接构造 RouteObject，模式串是**照抄** config/route.php 的。若 route.php 改了路径，
     * 上面的用例会继续绿而现实已经变了 —— 这里钉住被抄的那几处注册仍在原处（改了就红）。
     */
    #[Test]
    public function pinsRouteTableShapeStillMatches(): void
    {
        $routeFile = __DIR__ . '/../config/route.php';
        $this->assertFileExists($routeFile);
        $src = file_get_contents($routeFile);

        // 三条裸 Route::resource 是 /user /role /permission 的 {id} 路由来源，
        // 也是"9 条死 slug 被复活"的根因；它们必须在 /admin/v1 组内（:73 组起，:298 挂中间件）
        foreach (["Route::resource('/user'", "Route::resource('/role'", "Route::resource('/permission'"] as $needle) {
            $this->assertStringContainsString($needle, $src, "config/route.php 里找不到 {$needle}");
        }
        $this->assertStringContainsString("Route::put('/config/{id}'", $src);
        $this->assertStringContainsString("Route::post('/leaderboard/{hashid}/refresh'", $src);
    }
}
