<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\api\v1\controller\AuthController;
use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Webman\Route;

/**
 * 两条公开面的收口（2026-10-01 审计批次）：
 *
 * ① 操作日志 input 只脱敏凭据、不脱敏 PII —— phone / email / real_name / id_card / id_number
 *    原样落库，而同一份数据在 AdminUser 里是 encryptable 密文、在接口出参里是 138****5678。
 *    读它只需 get.admin/dashboard 这条已播种的低权 slug（DashboardController::getRecentLogs 直接
 *    toArray 外发，且结果在 Redis 里躺着 5 分钟）。
 *
 * ② POST /api/v1/auth/register 是匿名自助注册管理员：一次点击验证码即可建出 status=1 的 admin_user
 *    并当场签发 JWT，且 /api/v1 组无 OperationLog ⇒ 建号不留痕。已摘路由、保留控制器方法。
 */
class AdminPiiAndRegisterHardeningTest extends TestCase
{
    /** filterSensitive 是私有的；只反射这个纯函数，不碰 DB、不写库。 */
    private function filter(array $data): array
    {
        // 不加 setAccessible(true)：PHP 8.1 起它对私有方法已无作用，8.5 起调用本身是 deprecation。
        $method = new ReflectionMethod(\app\middleware\OperationLog::class, 'filterSensitive');

        return $method->invoke(new \app\middleware\OperationLog(), $data);
    }

    /**
     * 写入侧真实键名（AdminUser 的 phone/email/id_card 走 encryptable；real_name VARCHAR(50)；
     * game_user_identity.id_number 加密列）。real_name / id_card / id_number 切词后是
     * [real,name] / [id,card] / [id,number]，单字词表抓不到 ⇒ 必须整段比对才挡得住。
     */
    #[Test]
    public function adminPiiKeysAreMasked(): void
    {
        $pii = [
            'phone'      => '13812345678',
            'email'      => 'alice@example.com',
            'real_name'  => '张三',
            'id_card'    => '110101199001011234',
            'id_number'  => '110101199001011234',
            'contact_phone' => '13812345678',   // 前缀变体要走单字词表
            'realName'   => '张三',              // 驼峰要走复合词表
            'admin_id_card' => '110101199001011234',
        ];

        $out = $this->filter($pii);

        foreach (array_keys($pii) as $key) {
            $this->assertSame('***', $out[$key], "PII 键 {$key} 未脱敏：明文会从 dashboard 的 recent_logs 外泄");
        }
    }

    /**
     * 负控：打码不能连坐。
     *
     * username / role_name / status 是审计要留的正文（谁被改了、改成了什么）；
     * valid_card 证明复合词是**整段**比而不是 str_contains —— 子串匹配会把 validcard 里的 idcard 也打掉。
     */
    #[Test]
    public function nonPiiNeighboursSurvive(): void
    {
        $input = [
            'username'   => 'alice',
            'role_name'  => 'operator',
            'game_name'  => '斗地主',
            'status'     => 1,
            'valid_card' => 'yes',
            'keyword'    => '充值',
        ];

        $this->assertSame($input, $this->filter($input));
    }

    /**
     * 路由钉子：匿名注册入口不再挂载，但控制器方法按约定保留（便于日后改造后恢复）。
     *
     * 走真路由表（Route::dispatch）而不是读 route.php 文本 —— 文本式断言在「注释里写着路由、
     * 实际上没注册」时会假绿。必须隔进程跑：Route::load() 内部是 require_once，
     * 同进程第二次调用只会把静态路由表清成 0 条（见 PermissionSeedParityTest 的同类说明）。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function anonymousRegisterRouteIsGoneButLoginAndRefreshStay(): void
    {
        Route::load([__DIR__ . '/../config']);
        $this->assertNotEmpty(Route::getRoutes(), '路由未装载：Route::load 未生效');

        $this->assertNotSame(
            Dispatcher::FOUND,
            Route::dispatch('POST', '/api/v1/auth/register')[0],
            '匿名自助注册管理员的入口又回到路由表了'
        );

        // 负控：同组的 login/refresh 必须在，否则「把 /api/v1 组整个删掉」也能满足上面那条
        $this->assertSame(Dispatcher::FOUND, Route::dispatch('POST', '/api/v1/auth/login')[0]);
        $this->assertSame(Dispatcher::FOUND, Route::dispatch('POST', '/api/v1/auth/refresh')[0]);

        $this->assertTrue(
            method_exists(AuthController::class, 'register'),
            '控制器方法应保留（摘的只是路由），否则日后恢复要重写一遍'
        );
    }
}
