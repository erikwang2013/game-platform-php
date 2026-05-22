<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use support\Request;
use Webman\Http\Response;

class BackendEnhancementTest extends TestCase
{
    // ============================================================
    // 1. v() 辅助函数 — 逐源码验证（避免触发 webman 运行时）
    // ============================================================

    public function test_v_helper_function_exists_in_route_file(): void
    {
        $source = file_get_contents(__DIR__ . '/../config/route.php');
        $this->assertStringContainsString('function v(', $source, 'route.php 应定义 v() 函数');
        $this->assertStringContainsString('$request->apiVersion', $source, 'v() 应读取 apiVersion');
        $this->assertStringContainsString('apiVersion ??', $source, 'v() 应有 apiVersion 默认值回退');
        $this->assertStringContainsString('return (new $class)->', $source, 'v() 应实例化并调用控制器');
    }

    // ============================================================
    // 2. HealthController — 运行时测试
    // ============================================================

    public function test_health_controller_returns_correct_structure(): void
    {
        $controller = new \app\admin\controller\HealthController();
        $request = new Request('GET', '/health');
        $response = $controller->index($request);

        $body = json_decode($response->rawBody(), true);

        $this->assertEquals(0, $body['code']);
        $this->assertEquals('open-admin', $body['data']['app']);
        $this->assertEquals('1.0', $body['data']['version']);
        $this->assertEquals(PHP_VERSION, $body['data']['php']);
        $this->assertArrayHasKey('database', $body['data']);
        $this->assertArrayHasKey('redis', $body['data']);
        $this->assertArrayHasKey('elasticsearch', $body['data']);
        $this->assertArrayHasKey('timestamp', $body['data']);
        $this->assertIsInt($body['data']['timestamp']);
    }

    public function test_health_controller_values_are_strings(): void
    {
        $controller = new \app\admin\controller\HealthController();
        $request = new Request('GET', '/health');
        $response = $controller->index($request);
        $body = json_decode($response->rawBody(), true);

        $this->assertIsString($body['data']['database']);
        $this->assertIsString($body['data']['redis']);
        $this->assertIsString($body['data']['elasticsearch']);
    }

    public function test_health_controller_database_field_is_ok_or_unavailable(): void
    {
        $controller = new \app\admin\controller\HealthController();
        $request = new Request('GET', '/health');
        $response = $controller->index($request);
        $body = json_decode($response->rawBody(), true);

        $this->assertContains($body['data']['database'], ['ok', 'unavailable']);
    }

    // ============================================================
    // 3. 中间件接口实现
    // ============================================================

    public function test_cors_middleware_implements_interface(): void
    {
        $this->assertTrue(
            is_subclass_of(\app\middleware\Cors::class, \Webman\MiddlewareInterface::class),
            'Cors 应实现 MiddlewareInterface'
        );
    }

    public function test_rate_limit_middleware_implements_interface(): void
    {
        $this->assertTrue(
            is_subclass_of(\app\middleware\RateLimit::class, \Webman\MiddlewareInterface::class),
            'RateLimit 应实现 MiddlewareInterface'
        );
    }

    public function test_operation_log_middleware_implements_interface(): void
    {
        $this->assertTrue(
            is_subclass_of(\app\middleware\OperationLog::class, \Webman\MiddlewareInterface::class),
            'OperationLog 应实现 MiddlewareInterface'
        );
    }

    public function test_rate_limit_has_sensitive_config(): void
    {
        $reflection = new \ReflectionClass(\app\middleware\RateLimit::class);

        $refDefaultLimit = $reflection->getProperty('defaultLimit');
        $this->assertEquals(60, $refDefaultLimit->getDefaultValue());

        $refSensitive = $reflection->getProperty('sensitive');
        $sensitive = $refSensitive->getDefaultValue();
        $this->assertArrayHasKey('/api/auth/login', $sensitive);
        $this->assertEquals(10, $sensitive['/api/auth/login']['limit']);
        $this->assertArrayHasKey('/api/auth/register', $sensitive);
        $this->assertEquals(5, $sensitive['/api/auth/register']['limit']);
    }

    public function test_rate_limit_has_lua_script_for_atomicity(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/RateLimit.php');
        $this->assertStringContainsString('redis.call', $source, 'RateLimit 应使用 Lua 脚本确保原子性');
        $this->assertStringContainsString('ZREMRANGEBYSCORE', $source);
        $this->assertStringContainsString('ZCARD', $source);
    }

    // ============================================================
    // 4. 模型修改 — 源码级验证
    // ============================================================

    public function test_admin_user_source_contains_soft_deletes(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/AdminUser.php');
        $this->assertStringContainsString('SoftDeletes', $source);
        $this->assertStringContainsString('use SoftDeletes;', $source);
    }

    public function test_admin_user_source_contains_searchable(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/AdminUser.php');
        $this->assertStringContainsString('Searchable', $source);
        $this->assertStringContainsString('use Searchable;', $source);
    }

    public function test_admin_user_source_contains_to_searchable_array(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/AdminUser.php');
        $this->assertStringContainsString('toSearchableArray', $source);
        $this->assertStringContainsString("'username'", $source);
        $this->assertStringContainsString("'real_name'", $source);
    }

    public function test_operation_log_source_has_timestamps_disabled(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/model/OperationLog.php');
        $this->assertStringContainsString('$timestamps = false', $source);
    }

    // ============================================================
    // 5. 路由配置完整性
    // ============================================================

    public function test_route_file_contains_all_new_routes(): void
    {
        $content = file_get_contents(__DIR__ . '/../config/route.php');

        $expected = [
            '/health',
            'ConfigController',
            'LogController',
            'ProfileController',
            'ImportController',
            'UploadController',
            'HealthController',
            'OperationLog::class',
            'batchDestroy',
            'batchStatus',
            'logout',
        ];

        foreach ($expected as $needle) {
            $this->assertStringContainsString($needle, $content, "路由文件应包含: {$needle}");
        }
    }

    public function test_route_file_has_api_version_middleware(): void
    {
        $content = file_get_contents(__DIR__ . '/../config/route.php');
        $this->assertStringContainsString('ApiVersion::class', $content);
    }

    public function test_route_file_has_sensitive_batch_routes_after_resource(): void
    {
        $content = file_get_contents(__DIR__ . '/../config/route.php');
        $resourcePos = strpos($content, "Route::resource('/user'");
        $batchPos = strpos($content, 'batch/destroy');
        $this->assertGreaterThan(
            $resourcePos,
            $batchPos,
            '批处理路由应在 resource 之后注册'
        );
    }

    public function test_middleware_config_contains_cors_and_rate_limit(): void
    {
        $middlewares = require __DIR__ . '/../config/middleware.php';
        $this->assertIsArray($middlewares);
        $this->assertContains(\app\middleware\Cors::class, $middlewares, '全局中间件应包含 Cors');
        $this->assertContains(\app\middleware\RateLimit::class, $middlewares, '全局中间件应包含 RateLimit');
    }

    // ============================================================
    // 6. 控制器类结构验证
    // ============================================================

    public function test_all_new_controllers_exist(): void
    {
        $controllers = [
            \app\admin\controller\HealthController::class,
            \app\admin\controller\ConfigController::class,
            \app\admin\controller\LogController::class,
            \app\admin\controller\ProfileController::class,
            \app\admin\controller\UploadController::class,
            \app\admin\controller\ImportController::class,
        ];

        foreach ($controllers as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
        }
    }

    public function test_config_controller_has_crud_methods(): void
    {
        $methods = get_class_methods(\app\admin\controller\ConfigController::class);
        $this->assertContains('index', $methods);
        $this->assertContains('store', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('destroy', $methods);
    }

    public function test_profile_controller_has_required_methods(): void
    {
        $methods = get_class_methods(\app\admin\controller\ProfileController::class);
        $this->assertContains('updateProfile', $methods);
        $this->assertContains('updatePassword', $methods);
        $this->assertContains('logout', $methods);
    }

    public function test_user_controller_has_batch_methods(): void
    {
        $methods = get_class_methods(\app\admin\controller\UserController::class);
        $this->assertContains('batchDestroy', $methods, 'UserController 应有 batchDestroy 方法');
        $this->assertContains('batchStatus', $methods, 'UserController 应有 batchStatus 方法');
    }

    // ============================================================
    // 7. 安全验证
    // ============================================================

    public function test_operation_log_input_filters_passwords(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/OperationLog.php');
        $this->assertStringContainsString('password', $source);
        $this->assertStringContainsString('old_password', $source);
        $this->assertStringContainsString('new_password', $source);
    }

    public function test_operation_log_has_try_catch(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/OperationLog.php');
        $this->assertStringContainsString('try {', $source, 'OperationLog 应有异常保护');
        $this->assertStringContainsString('catch', $source);
    }

    public function test_operation_log_generates_snowflake_id(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/OperationLog.php');
        $this->assertStringContainsString('SnowflakeService::generate()', $source, 'OperationLog 应生成 Snowflake ID');
    }

    public function test_health_endpoint_does_not_leak_error_details(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/admin/controller/HealthController.php');

        $catchBlocks = [];
        preg_match_all('/catch\s*\([^)]*\)\s*\{[^}]*\}/s', $source, $catchBlocks);

        foreach ($catchBlocks[0] as $block) {
            $this->assertStringNotContainsString(
                '$e->getMessage()',
                $block,
                'Health 端点 catch 块不应暴露 getMessage()'
            );
        }
    }

    public function test_admin_auth_has_blacklist_check(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/AdminAuth.php');
        $this->assertStringContainsString('jwt_blacklist', $source, 'AdminAuth 应包含 JWT 黑名单检查');
    }

    public function test_cors_response_is_assigned_correctly(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/middleware/Cors.php');
        $this->assertStringContainsString('$response = $response->withHeaders', $source);
    }

    // ============================================================
    // 8. 版权声明
    // ============================================================

    public function test_all_new_files_have_copyright_header(): void
    {
        $newFiles = [
            '/app/middleware/Cors.php',
            '/app/middleware/RateLimit.php',
            '/app/middleware/OperationLog.php',
            '/app/admin/controller/HealthController.php',
            '/app/admin/controller/ConfigController.php',
            '/app/admin/controller/LogController.php',
            '/app/admin/controller/ProfileController.php',
            '/app/admin/controller/UploadController.php',
            '/app/admin/controller/ImportController.php',
        ];

        $basePath = dirname(__DIR__);

        foreach ($newFiles as $file) {
            $content = file_get_contents($basePath . $file);
            $this->assertStringContainsString(
                'Copyright (c) 2026 erik',
                $content,
                "{$file} 应包含版权声明"
            );
        }
    }
}
