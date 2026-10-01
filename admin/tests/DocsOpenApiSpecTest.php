<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\DocsController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * /api/docs 的 OpenAPI 契约得真能构建出来（2026-10-01）。
 *
 * 钉两颗：
 * 1. 它不再因 DocsController::path() 第 3 参（`array $notes`，非 nullable）传 null 而抛 TypeError。
 *    该缺陷让 `/api/docs` 对任何过鉴权的调用者恒 500 —— 而冒烟只打了未登录的 401，控制器从没被执行过，
 *    所以藏了很久没人发现。
 * 2. paths 里不再有匿名注册条目（随 config/route.php 摘除）—— 有人把路由加回来，这里也红。
 */
class DocsOpenApiSpecTest extends TestCase
{
    #[Test]
    public function openApiSpecBuildsAndHasNoGhostRegisterEndpoint(): void
    {
        $controller = new DocsController();
        /** @var array<string, mixed> $spec */
        $spec = (new ReflectionMethod($controller, 'buildSpec'))->invoke($controller);

        $paths = $spec['paths'] ?? null;
        $this->assertIsArray($paths, 'buildSpec() 应当返回带 paths 的规范数组');
        $this->assertNotEmpty($paths, 'paths 不能为空 —— 空了说明构建其实已经坏了');

        // 正控制：认一条活着的路由，防「整体变空也算过」
        $this->assertArrayHasKey('/api/v1/auth/login', $paths);

        $this->assertArrayNotHasKey('/api/v1/auth/register', $paths, '匿名注册路由已摘除，契约里不该再有这个幽灵端点');
    }
}
