<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\AuthController;
use app\middleware\UserAuth;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * UserAuth 必须把「2FA 待验证票据」挡在门外。
 *
 * 开了 2FA 的账号登录时只发 scope=pending_2fa 的 600 秒票据（AuthController::login），
 * 它的唯一用途是去 /api/v1/2fa/verify 换发正式令牌。中间件若照单全收，知道密码、
 * 算不出 TOTP 的人就能拿它当 Bearer 打提现/兑换等全部受保护端点 = 绕过第二因子。
 *
 * 攻击链端到端复现：真登录（真库真口令）拿票据 → 真中间件 → 断言 401。
 * 负例（access token 照旧放行）是同一道闸的另一半：只钉「拒」，把闸口写成「什么都拒」也是假绿。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class UserAuthPending2faTest extends TestCase
{
    use CaptchaTestHelper;

    private const PASSWORD = 'correct-horse-battery-staple';

    private static bool $booted = false;

    private int $userId = 0;
    private string $username = '';

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过中间件鉴权真库用例）：' . $e->getMessage());
        }

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId   = SnowflakeService::generate();
        $this->username = 'userauth_2fa_' . $this->userId;

        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => $this->username,
            'password' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
        ]);
        // 只做「已开 2FA」这个开关：本用例不验 TOTP，secret 用任意非空串
        Db::table('user_2fa')->insert([
            'id'         => SnowflakeService::generate(),
            'user_id'    => $this->userId,
            'secret'     => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }
        Db::table('user_2fa')->where('user_id', $this->userId)->delete();
        Db::table('user')->where('id', $this->userId)->delete();
    }

    /** 缺陷复现点：登录拿到的 2FA 待验证票据不得通过 UserAuth */
    #[Test]
    public function pendingTwoFactorTicketCannotReachProtectedEndpoints(): void
    {
        $login = $this->login();
        $this->assertTrue(
            $login['data']['require_2fa'] ?? false,
            '已开 2FA 的账号登录应只下发票据（这是攻击序列的第一步）：' . json_encode($login)
        );

        $body = $this->throughUserAuth($login['data']['pending_2fa_token']);

        $this->assertSame(
            401,
            $body['code'],
            'scope=pending_2fa 的票据必须被 UserAuth 拒绝，否则等于绕过第二因子：' . json_encode($body)
        );
    }

    /** 负例：正式 access token 必须照旧放行（闸口不能被开成「什么都拒」） */
    #[Test]
    public function accessTokenStillPasses(): void
    {
        $token = jwt_wrapper()->create(['sub' => $this->userId, 'username' => $this->username]);

        $body = $this->throughUserAuth($token);

        $this->assertSame(0, $body['code'], 'access token 必须放行：' . json_encode($body));
        $this->assertSame($this->userId, $body['data']['user_id'] ?? 0, '放行时中间件应注入 $request->userId');
    }

    /** @return array<string, mixed> 登录响应体（走真实 AuthController，非手工造票据） */
    private function login(): array
    {
        $request = new Request("POST /api/v1/auth/login HTTP/1.1\r\nHost: localhost\r\n\r\n");
        // 登录已加强制点击验证码：不带 captcha_key/clicks 会 422「验证码错误」
        $request->setPost(['username' => $this->username, 'password' => self::PASSWORD] + $this->captchaParams());

        return json_decode((new AuthController())->login($request)->rawBody(), true);
    }

    /**
     * 放行则返回 $next 的注入读数，拦截则返回中间件自带的 401 体。
     *
     * @return array<string, mixed>
     */
    private function throughUserAuth(string $token): array
    {
        $request = new Request(
            "POST /api/v1/withdraw/apply HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer {$token}\r\n\r\n"
        );

        $response = (new UserAuth())->process($request, static fn ($req) => json([
            'code'    => 0,
            'message' => 'next-called',
            'data'    => ['user_id' => $req->userId ?? 0],
        ]));

        return json_decode($response->rawBody(), true);
    }

    /**
     * 让 support\Db 指向测试库（与 ExchangeWalletIntegrationTest 同一套自保做法，此处复制而非共享）。
     *
     * tests/bootstrap.php 只把测试库配置写进了一个局部数组；而 support\Db 首次被 autoload 时
     * 其文件尾部的 Webman\Database\Initializer::init(config('database')) 会再建一个 capsule
     * 并 setAsGlobal —— 用的是【开发库】的 config('database')。所以必须先把这次一次性初始化
     * 烧掉，再自己 setAsGlobal，否则查询打的是开发库。
     */
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
        // 凭据优先取环境变量（GP_DB_USER/GP_DB_PASS），缺省回落到 config('database') 即 .env 的口令
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
