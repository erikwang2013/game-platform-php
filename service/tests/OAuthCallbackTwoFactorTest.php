<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\OAuthController;
use app\middleware\UserAuth;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/** exchangeCode 替身：零网络返回固定 open_id，其余流程（state 校验/绑定查询/签发）全走真代码 */
class StubOAuthController extends OAuthController
{
    public static string $openId = '';

    protected function exchangeCode(string $provider, string $code, ?string $codeVerifier = null): array
    {
        return [
            'provider' => $provider,
            'open_id'  => self::$openId,
            'nickname' => 'Stub User',
            'email'    => '',
            'avatar'   => '',
        ];
    }
}

/**
 * OAuth 回调必须与密码登录走**同一套** 2FA 门（M1）。
 *
 * 原缺口：已绑定第三方身份的账号，回调分支直接签发无 scope 的正式令牌，
 * 全文件没有一处 2FA 判定 ⇒ 攻击者只要持有该第三方身份（或共享设备登录态），
 * 即可拿正式令牌提现/兑换，第二因子形同虚设。
 *
 * 覆盖面按「真库真流程」：真 UserOauth 绑定行 → 真 callback()（state 走真 Redis）→
 * 断言响应形状；再把签出来的票据喂给真 UserAuth，证明它与密码路是同一张半成品票
 * （而不是自创的第二套弱票据）。负例（未开 2FA 仍发正式令牌）是同一道闸的另一半：
 * 只钉「拒」，把闸口写成「OAuth 一律不发令牌」也是假绿。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class OAuthCallbackTwoFactorTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private string $openId = '';
    private string $state = '';

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 成功响应里的 user.id 要过 encodeId()，依赖 hashids 容器绑定（webman 插件 bootstrap
        // 注册的，PHPUnit 下要手动起；同 GroupJoinLeaveRejoinTest）
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过 OAuth 2FA 用例）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId = SnowflakeService::generate();
        $this->openId = 'oauth_2fa_' . $this->userId;
        $this->state  = bin2hex(random_bytes(16));

        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'oauth_2fa_' . $this->userId,
            'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'status'   => 1,
        ]);
        Db::table('user_oauth')->insert([
            'id'       => SnowflakeService::generate(),
            'user_id'  => $this->userId,
            'provider' => 'google',
            'open_id'  => $this->openId,
        ]);

        StubOAuthController::$openId = $this->openId;

        try {
            Redis::setex('oauth_state:' . $this->state, 600, 'google');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用（跳过 OAuth 2FA 用例）：' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }
        Db::table('user_2fa')->where('user_id', $this->userId)->delete();
        Db::table('user_oauth')->where('user_id', $this->userId)->delete();
        Db::table('user')->where('id', $this->userId)->delete();
        try {
            Redis::del('oauth_state:' . $this->state);
        } catch (\Throwable) {
        }
    }

    /** 缺陷复现点：已开 2FA 的账号走 OAuth 回调不得拿到正式令牌 */
    #[Test]
    public function twoFactorEnabledUserGetsPendingTicketNotAccessToken(): void
    {
        Db::table('user_2fa')->insert([
            'id'         => SnowflakeService::generate(),
            'user_id'    => $this->userId,
            'secret'     => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => 1,
        ]);

        $body = $this->oauthCallback();

        $this->assertTrue($body['data']['require_2fa'] ?? false, '2FA 账号的 OAuth 回调应只下发票据：' . json_encode($body));
        $this->assertNotEmpty($body['data']['pending_2fa_token'] ?? '', '应签发 pending_2fa 票据');
        $this->assertArrayNotHasKey('access_token', $body['data'], '不得签发正式令牌（否则等于绕过第二因子）');
        $this->assertArrayNotHasKey('refresh_token', $body['data'], 'refresh token 同样不得签发');
    }

    /** 同一条闸的另一半：票据必须被 UserAuth 拒收（与密码登录的 pending 票据同一机制） */
    #[Test]
    public function oauthPendingTicketCannotReachProtectedEndpoints(): void
    {
        Db::table('user_2fa')->insert([
            'id'         => SnowflakeService::generate(),
            'user_id'    => $this->userId,
            'secret'     => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => 1,
        ]);

        $ticket = (string) ($this->oauthCallback()['data']['pending_2fa_token'] ?? '');
        $this->assertNotSame('', $ticket, '前置条件：应拿到票据');

        $body = $this->throughUserAuth($ticket);

        $this->assertSame(401, $body['code'], 'OAuth 签出的半成品票据必须被 UserAuth 拒绝：' . json_encode($body));
    }

    /** 负例：未开 2FA 的账号照旧签发正式令牌（闸口不能被开成「OAuth 一律不发令牌」） */
    #[Test]
    public function withoutTwoFactorAccessTokenIsStillIssued(): void
    {
        $body = $this->oauthCallback();

        $this->assertNotEmpty($body['data']['access_token'] ?? '', '未开 2FA 应照旧签发正式令牌：' . json_encode($body));
        $this->assertArrayNotHasKey('require_2fa', $body['data']);
    }

    /** @return array<string, mixed> */
    private function oauthCallback(): array
    {
        $request = new Request("POST /api/v1/auth/oauth/google/callback HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['code' => 'stub-code', 'state' => $this->state]);

        return json_decode((new StubOAuthController())->callback($request, 'google')->rawBody(), true);
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
