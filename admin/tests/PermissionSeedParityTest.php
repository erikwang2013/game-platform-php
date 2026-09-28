<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webman\Route;

/**
 * 权限种子的**双向差集钉子**：install.sql 里 type=3 的 slug 集 ⇄ 运行时归一 slug 集。
 *
 * 单向检查（「种子里每条都存在于路由」）挡不住新增端点：新端点没进种子时它照样绿，
 * 而那个端点在线上只有持 `*` 的角色能用 —— 这正是 131 条 slug 缺口当初的形态。
 * 两个方向都断言空，才叫「可授予集合与路由集合相等」。
 *
 * 差集两边的口径：
 *  A) 种子 → 运行时：种子里不得有死行（删掉/改名的端点留下永远授不出去的 slug）。
 *     排除 `*` —— 它是「全部权限」通配符（AdminPermission.php:32 直接短路），不是某条路由。
 *  B) 运行时 → 种子：**每条挂 AdminPermission 的路由**都必须有一行可授予的 slug。
 *     **中间件比的是 slug，不是路径前缀** —— 判据取「是否挂 AdminPermission」，不取 `/admin/v1/` 前缀：
 *     `/metrics` 与 `/api/docs` 挂在根上（route.php:48/:65），不在 /admin/v1 组里，但同样走这套
 *     slug 比对；按前缀取集合会把它们漏出钉子之外（本批加这两条种子时，正是这个钉子先把它们
 *     报成「死行」、从而暴露出取集合的口径错了 —— 钉子在工作，不是种子写错了）。
 *
 * ⚠ slug 的算法不在这里重写一遍字面量，而是**反射 AdminPermission 自己的两个私有方法**
 * （permissionPath / stripVersionSegment）：种子是按它生成的，钉子也必须按它算，
 * 否则中间件改了归一规则而测试还在验旧规则，两边一起绿。
 *
 * 为什么必须 RunInSeparateProcess：Route::load() 每次调用先清空 dispatcher/allRoutes 等静态状态，
 * 再用 require_once 加载 config/route.php ⇒ 同一进程里第二次调用拿回 0 条路由。
 * 本类若和 GameRouteTest 同进程，谁先跑谁把对方打成「路由全未命中」的假红。
 * 隔进程跑：路由静态状态不与任何人共享。
 */
class PermissionSeedParityTest extends TestCase
{
    private const SEED_FILE = __DIR__ . '/../../install/install.sql';

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function seedSlugsAndRuntimeSlugsAreEqualInBothDirections(): void
    {
        $runtime = $this->runtimeAdminSlugs();
        // `*` 从种子侧排除：它是通配符、不对应任何路由（下面 wildcardPermissionRowStillExists 单独钉它还在）。
        $seed    = array_values(array_diff($this->seedGrantableSlugs(), ['*']));

        // 两侧都要先证明「真的读到东西了」，否则空集之间的差集恒为空 —— 假绿。
        $this->assertNotEmpty($runtime, '/admin/v1 路由一条都没枚举到：Route::load 没生效或归一算法已改');
        $this->assertGreaterThan(100, count($seed), 'install.sql 里解析出的可授予 slug 太少，正则多半没匹配上');

        $deadSeedRows = array_values(array_diff($seed, $runtime));
        $unseeded     = array_values(array_diff($runtime, $seed));

        $this->assertSame([], $deadSeedRows,
            "install.sql 有路由里不存在的 type=3 slug（死行，授不出去）：\n  " . implode("\n  ", $deadSeedRows));
        $this->assertSame([], $unseeded,
            "以下 /admin/v1 路由没有可授予的种子 slug（新端点漏进 install.sql）：\n  " . implode("\n  ", $unseeded));
    }

    /** `*` 必须还在种子里：上面把它排除掉了，这里钉住它是**被显式排除**的，不是不小心漏了。 */
    #[Test]
    public function wildcardPermissionRowStillExists(): void
    {
        $this->assertContains('*', $this->seedGrantableSlugs());
    }

    /**
     * 运行时归一 slug 集（只取挂 AdminPermission 的路由）。
     * 与 routes 生成脚本 /tmp/seed_dump.php 同构，只是归一那两步改为反射真实实现。
     */
    private function runtimeAdminSlugs(): array
    {
        Route::load([__DIR__ . '/../config']);

        $middleware = new \app\middleware\AdminPermission();
        $permissionPath = new \ReflectionMethod($middleware, 'permissionPath');
        $permissionPath->setAccessible(true);
        $stripVersion = new \ReflectionMethod($middleware, 'stripVersionSegment');
        $stripVersion->setAccessible(true);

        $request = new \support\Request("GET /admin/v1/x HTTP/1.1\r\nHost: localhost\r\n\r\n");

        $slugs = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array(\app\middleware\AdminPermission::class, $route->getMiddleware(), true)) {
                continue;
            }
            $request->route = $route;                       // permissionPath 只读这一个属性
            $path = $stripVersion->invoke($middleware, $permissionPath->invoke($middleware, $request));

            foreach ($route->getMethods() as $method) {
                $slugs[strtolower($method) . '.' . trim($path, '/')] = true;
            }
        }

        $this->assertNotEmpty(Route::getRoutes(), '路由未装载：Route::load() 未生效');

        return array_keys($slugs);
    }

    /**
     * install.sql 里 type=3（可授予）的 slug 集。行形如
     *   (21000000000000101, '0', '中文名', 'get.admin/xxx', 3, '', '', 100, NOW(), NOW())
     * 收窄到 `game_admin_permission` 的 INSERT 语句块内，且 id 至少 15 位（雪花；菜单 id 是 17 位），
     * 避免误吞别处的 INSERT。parent_id 两种写法都要认：文件里本来就混着
     * `(id, 21000000000000002, '查看用户', …)` 与 `(id, '0', '全部权限', …)`。
     */
    private function seedGrantableSlugs(): array
    {
        $sql = file_get_contents(self::SEED_FILE);
        $this->assertNotFalse($sql, 'install.sql 读不到');

        $slugs = [];
        foreach ($this->seedBlocks($sql) as $block) {
            preg_match_all(
                "/\(\s*\d{15,},\s*(?:'[^']*'|\d+),\s*'(?:[^'\\\\]|\\\\.)*',\s*'((?:[^'\\\\]|\\\\.)*)',\s*3\s*,/",
                $block,
                $m
            );
            foreach ($m[1] as $slug) {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }

    /** @return string[] game_admin_permission 的每条 INSERT 语句 */
    private function seedBlocks(string $sql): array
    {
        preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`game_admin_permission`.*?;\s*\n/s', $sql, $blocks);

        return $blocks[0];
    }
}
