<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\GameController;
use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webman\Route;

/**
 * 管理端游戏路由接线
 *
 * 管理端 HarmonyOS 客户端的游戏大厅/详情两屏改为调用 /admin/v1/game/*，
 * 这里钉住路由真实接线：静态段不被占位符遮蔽，且不落到无鉴权的 /api/v1 公开组。
 */
class GameRouteTest extends TestCase
{
    /**
     * Route::load() 只接受目录（内部拼 /route.php），且用的是 require_once。
     * 每次调用还会清空 dispatcher 等静态状态，所以不能放进 setUp() 逐用例调：
     * 第二次 require_once 不生效，重建出来的 dispatcher 会是 0 条路由，
     * 表现为"路由全都没命中"的假红。类级加载一次即可。
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Route::load([__DIR__ . '/../config']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // 路由装载失败必须响亮地失败，而不是让下面每条断言都报"未命中路由"
        $this->assertNotEmpty(Route::getRoutes(), '路由未装载：Route::load 未生效');
    }

    /**
     * webman 注册进 FastRoute 的处理句柄是 ['callback' => 回调, 'route' => RouteObject]，
     * RouteObject 上带着这条路由的中间件链——正好用来钉住"必须走 admin 鉴权"。
     */
    private function match(string $method, string $path): array
    {
        $info = Route::dispatch($method, $path);
        $this->assertSame(Dispatcher::FOUND, $info[0], "{$method} {$path} 未命中路由");

        return $info[1];
    }

    private function handler(string $method, string $path): mixed
    {
        return $this->match($method, $path)['callback'];
    }

    #[Test]
    public function gameListIsNotShadowedByHashidPlaceholder(): void
    {
        // /game/{hashid} 与 /game/list 同为 GET：静态段必须优先，否则列表接口会 404
        $this->assertSame([GameController::class, 'list'], $this->handler('GET', '/admin/v1/game/list'));
    }

    #[Test]
    public function gameDetailResolvesByHashid(): void
    {
        $this->assertSame([GameController::class, 'detail'], $this->handler('GET', '/admin/v1/game/AbCd1234'));
    }

    #[Test]
    public function gameLaunchResolvesOnPost(): void
    {
        $this->assertSame([GameController::class, 'launch'], $this->handler('POST', '/admin/v1/game/launch'));
    }

    #[Test]
    public function gameRoutesCarryAdminAuthChain(): void
    {
        // 挂 /admin/v1 的全部意义就是继承这三层中间件；某个路由若被挪出组，
        // 鉴权会静默消失（/api/v1 是公开组），所以逐条钉住。
        foreach ([['GET', '/admin/v1/game/list'], ['GET', '/admin/v1/game/AbCd1234'], ['POST', '/admin/v1/game/launch']] as [$method, $path]) {
            $middlewares = $this->match($method, $path)['route']->getMiddleware();

            $this->assertContains(\app\middleware\AdminAuth::class, $middlewares, "{$method} {$path} 缺 AdminAuth");
            $this->assertContains(\app\middleware\AdminPermission::class, $middlewares, "{$method} {$path} 缺 AdminPermission");
        }
    }

    #[Test]
    public function gameRoutesAreNotExposedOnPublicApiGroup(): void
    {
        // /api/v1 是无鉴权公开组（只有 captcha/auth）；游戏端点挂那里等于公开
        foreach ([['GET', '/api/v1/game/list'], ['GET', '/api/v1/game/AbCd1234'], ['POST', '/api/v1/game/launch']] as [$method, $path]) {
            $this->assertSame(
                Dispatcher::NOT_FOUND,
                Route::dispatch($method, $path)[0],
                "{$method} {$path} 不应存在：管理端游戏端点必须走 /admin/v1 的鉴权链"
            );
        }
    }
}
