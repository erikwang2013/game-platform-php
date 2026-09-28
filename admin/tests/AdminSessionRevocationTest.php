<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\ProfileController;
use app\api\v1\controller\AuthController;
use app\middleware\AdminAuth;
use app\middleware\AdminPermission;
use app\model\AdminUser;
use common\SnowflakeService;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Redis;
use support\Request;
use support\Response;

/**
 * 管理端「会话吊销」三族回归护栏（每条都有改前红/改后绿的反证）：
 *
 *  [4] 账号级开关：停用/删除必须立即生效。注意 perm 缓存里可能还留着降权前的 '*'，
 *      所以状态判断必须排在读缓存**之前** —— 否则被停用者仍能全权访问最长 60 秒。
 *  [2] refresh 令牌一次性：轮换（旧 jti 入黑名单）＋ 只有 token_type=refresh 的令牌可换新会话，
 *      否则 2 小时有效的 access 令牌能换出 14 天有效的 refresh 令牌。
 *  [1] 登出连带吊销同会话的 refresh：客户端三个端都只送 access（不带 refresh_token），
 *      故服务端在 trackSession 里存了 access→refresh 映射，登出据此找到并吊销。
 *
 * 令牌一律由控制器自身的私有工厂（反射 getJWT）铸造，保证与校验时同一份配置与密钥。
 * 需要 MySQL / Redis 的用例各自 skip（与套件内其它用例同口径），不静默通过。
 */
class AdminSessionRevocationTest extends TestCase
{
    /** 不存在于库中的管理员 ID：刷新/登录路径不依赖库中已有数据，且不会写 last_login_at */
    private const SUB = 99999999999999999;

    private const PASSWORD = 'Probe1234';

    /** @var string[] 用例创建的管理员用户名，tearDown 据此清库与清 Redis 计数键 */
    private array $probeUsernames = [];

    /** @var string[] 用例写入的 Redis 键 */
    private array $redisKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->probeUsernames as $username) {
            try {
                $user = AdminUser::withTrashed()->where('username', $username)->first();
                if ($user) {
                    $this->redisKeys[] = "perm:{$user->id}";
                    $this->redisKeys[] = "user_tokens:{$user->id}";
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
        $this->redisKeys = [];

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // [4] 账号状态：停用/删除必须立即生效，且不能被 perm 缓存拖住
    // ---------------------------------------------------------------

    /**
     * 反证：改前 → 200（缓存的 "*" 直接放行）；改后 → 403。
     * 这正是"停用"的实际攻击面：管理员先正常工作（缓存被写成 '*'），再被停用。
     */
    #[Test]
    public function disabledAdminIsRejectedEvenWithWarmPermissionCache(): void
    {
        $this->requireRedis();
        $user = $this->createAdmin(0);
        $this->warmPermissionCache((int) $user->id, ['*']);

        $body = $this->runPermission('GET', '/admin/v1/user', (int) $user->id);

        $this->assertSame(
            403,
            $body['code'],
            '被停用的管理员持有效令牌必须被拒：状态检查若排在 perm 缓存之后，缓存里的 "*" 会让其继续全权访问最长 60 秒'
        );
    }

    /** 负例：启用的管理员不得被新加的闸口误伤 */
    #[Test]
    public function enabledAdminStillPassesWithWarmPermissionCache(): void
    {
        $this->requireRedis();
        $user = $this->createAdmin(1);
        $this->warmPermissionCache((int) $user->id, ['*']);

        $body = $this->runPermission('GET', '/admin/v1/user', (int) $user->id);

        $this->assertSame(0, $body['code'], '正常启用的管理员必须照常放行，不得把闸口开成"什么都拒"');
    }

    /** 删除的管理员：改前在缓存命中期同样放行，改后立即 403（原有 403 语义不回退） */
    #[Test]
    public function deletedAdminIsRejectedEvenWithWarmPermissionCache(): void
    {
        $this->requireRedis();
        $user = $this->createAdmin(1, deleted: true);
        $this->warmPermissionCache((int) $user->id, ['*']);

        $body = $this->runPermission('GET', '/admin/v1/user', (int) $user->id);

        $this->assertSame(403, $body['code'], '已删除的管理员必须被拒（软删除后 find() 取不到，权限为空）');
    }

    /** 护栏：状态闸口之外，method.path slug 比对本身不得被改动破坏 */
    #[Test]
    public function pathPermissionComparisonStillEnforced(): void
    {
        $this->requireRedis();
        $user = $this->createAdmin(1);
        $id = (int) $user->id;

        $this->warmPermissionCache($id, ['get.admin/user']);
        $this->assertSame(0, $this->runPermission('GET', '/admin/v1/user', $id)['code'], '缓存里有对应 slug 时必须放行');

        $this->warmPermissionCache($id, ['get.admin/other']);
        $this->assertSame(403, $this->runPermission('GET', '/admin/v1/user', $id)['code'], '缓存里没有对应 slug 时必须拒绝');
    }

    // ---------------------------------------------------------------
    // [2] 刷新令牌：轮换 + 只认 refresh 类型
    // ---------------------------------------------------------------

    /**
     * 反证：改前第二次使用同一枚 refresh 令牌仍返回 200（可无限续期）；改后 401。
     * 末段是负例，防止"一次性"做过头把整个会话刷成不可续。
     */
    #[Test]
    public function refreshTokenIsSingleUse(): void
    {
        $refresh = $this->mintRefreshToken();

        $first = $this->refresh($refresh);
        $this->assertSame(0, $first['code'], '首次刷新应成功');
        $rotated = (string) ($first['data']['refresh_token'] ?? '');
        $this->assertNotSame($refresh, $rotated, '刷新必须轮换出新的 refresh 令牌');

        // 护栏（非红绿项）：轮换交给 vendor 的 refresh() 之后，新 refresh 的有效期仍须是 refresh_expire。
        // 一旦退化成 access 的 default_expire（2 小时），管理员每 2 小时被强制重新登录，且症状极隐蔽。
        // 必须在下面把它也刷掉之前读：refresh() 会把上一枚 jti 拉黑，用过的令牌再 decode 就抛异常
        $payload = $this->jwt()->decode($rotated, true);
        $this->assertSame(
            (int) (config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600),
            (int) ($payload['exp'] ?? 0) - (int) ($payload['iat'] ?? 0),
            '轮换出的 refresh 令牌必须保持 refresh_expire 的有效期'
        );

        $replayed = $this->refresh($refresh);
        $this->assertSame(401, $replayed['code'], '同一枚 refresh 令牌第二次使用必须被拒（旧 jti 已入黑名单）');
        $this->assertEmpty($replayed['data']['access_token'] ?? '', '被拒时不得下发新令牌');

        $this->assertSame(0, $this->refresh($rotated)['code'], '轮换后的新 refresh 令牌必须可用：不得把会话刷成一次性');
    }

    /** 反证：改前 access 令牌换回 200 + 一枚 14 天有效的 refresh；改后 401 */
    #[Test]
    public function accessTokenCannotBeExchangedForRefreshToken(): void
    {
        $access = $this->jwt()->encode(['sub' => self::SUB, 'username' => 'probe']);

        $body = $this->refresh($access);

        $this->assertSame(
            401,
            $body['code'],
            'access 令牌不得当 refresh 使用：decode(allowRefresh) 只是"允许"refresh 类型，不显式要求就等于拿 2 小时换 14 天'
        );
        $this->assertEmpty($body['data']['refresh_token'] ?? '', '被拒时不得下发 refresh 令牌');
    }

    // ---------------------------------------------------------------
    // [1] 登出：连带吊销同会话的 refresh 令牌
    // ---------------------------------------------------------------

    /**
     * 走真实登录（验证码 + 建行 + 口令校验），证明 trackSession 的写侧确实落了映射，
     * 而不只是"登出会去查映射"。反证：改前 refresh 仍可用（200），改后 401。
     */
    #[Test]
    public function logoutRevokesRefreshTokenFromLoginSession(): void
    {
        $user = $this->createAdmin(1);
        $body = json_decode($this->login($user->username)->rawBody(), true);
        $this->assertSame(0, $body['code'], '登录失败，无法进入会话吊销断言：' . ($body['message'] ?? ''));

        $access  = (string) $body['data']['access_token'];
        $refresh = (string) $body['data']['refresh_token'];
        $this->redisKeys[] = 'session_refresh:' . md5($access);

        $this->logout($access);

        $this->assertSame(
            401,
            $this->refresh($refresh)['code'],
            '登出必须连带吊销同会话的 refresh 令牌：客户端只送 access，服务端不查映射的话 refresh 会让会话原地复活'
        );
        $this->assertSame(401, $this->adminAuth($access)['code'], '登出后 access 仍须被 AdminAuth 拒（原有 md5 黑名单不得回退）');
    }

    /**
     * 会话上限（3）必须能真正踢掉最旧会话：只拉黑 access 的话，被踢的客户端拿 refresh 一刷就复活。
     * 反证：改前最旧会话的 refresh 仍返回 200；改后 401。
     *
     * 四次登录之间 sleep(1)：zset 的 score 是整秒时间戳，同一秒内的成员会并列，zrange(0,0) 退化成
     * 按 md5 字典序取，变成"随机踢一个" —— 那样断言就只能在"踢掉了某个会话"和"踢掉了最旧的"之间二选一。
     */
    #[Test]
    public function sessionLimitEvictsOldestSessionEntirely(): void
    {
        $user = $this->createAdmin(1);
        $sessions = [];
        for ($i = 0; $i < 4; $i++) {
            if ($i > 0) {
                sleep(1);
            }
            $body = json_decode($this->login($user->username)->rawBody(), true);
            $this->assertSame(0, $body['code'], '第 ' . ($i + 1) . ' 次登录失败：' . ($body['message'] ?? ''));

            $access = (string) $body['data']['access_token'];
            $sessions[] = [$access, (string) $body['data']['refresh_token']];
            $this->redisKeys[] = 'session_refresh:' . md5($access);
        }

        [$oldAccess, $oldRefresh] = $sessions[0];
        $this->assertSame(401, $this->adminAuth($oldAccess)['code'], '超限后最旧会话的 access 令牌应被拉黑（原有行为）');
        $this->assertSame(
            401,
            $this->refresh($oldRefresh)['code'],
            '被驱逐的会话必须连 refresh 一起吊销：只杀 access 的话刷一次就复活，3 个会话的上限形同虚设'
        );
        $this->assertSame(0, $this->refresh($sessions[3][1])['code'], '未被驱逐的最新会话不得被误伤');
    }

    /** 注册入口同样登记会话：证明 register 的 trackSession 调用点接线正确（改错参数即 TypeError） */
    #[Test]
    public function logoutRevokesRefreshTokenFromRegisterSession(): void
    {
        [$captchaKey, $clicks] = $this->solveCaptcha();
        $username = $this->probeUsername('reg');

        $request = new Request("POST /api/v1/auth/register HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'username'    => $username,
            'password'    => self::PASSWORD,
            'real_name'   => 'revocation_probe',
            'captcha_key' => $captchaKey,
            'clicks'      => $clicks,
        ]);
        $body = json_decode((new AuthController())->register($request)->rawBody(), true);
        $this->assertSame(0, $body['code'], '注册失败：' . ($body['message'] ?? ''));

        $access  = (string) $body['data']['access_token'];
        $refresh = (string) $body['data']['refresh_token'];
        $this->redisKeys[] = 'session_refresh:' . md5($access);

        $this->logout($access);

        $this->assertSame(401, $this->refresh($refresh)['code'], '注册会话的 refresh 令牌同样必须能被登出吊销');
    }

    // ---------------------------------------------------------------
    // 辅助
    // ---------------------------------------------------------------

    /** 走 AdminPermission 中间件；返回响应体，code=0 表示放行到 $next */
    private function runPermission(string $method, string $path, int $adminId): array
    {
        $request = new Request("{$method} {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->adminId = $adminId;

        $response = (new AdminPermission())->process($request, static fn(): Response => json(['code' => 0, 'message' => 'passed', 'data' => []]));

        return json_decode($response->rawBody(), true);
    }

    /** @return array<string, mixed> */
    private function refresh(string $token): array
    {
        $request = new Request("POST /api/v1/auth/refresh HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['refresh_token' => $token]);

        return json_decode((new AuthController())->refresh($request)->rawBody(), true);
    }

    private function login(string $username): Response
    {
        [$captchaKey, $clicks] = $this->solveCaptcha();

        $request = new Request("POST /api/v1/auth/login HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'username'    => $username,
            'password'    => self::PASSWORD,
            'captcha_key' => $captchaKey,
            'clicks'      => $clicks,
        ]);

        return (new AuthController())->login($request);
    }

    private function logout(string $accessToken): void
    {
        $request = new Request("POST /admin/v1/profile/logout HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer {$accessToken}\r\n\r\n");
        (new ProfileController())->logout($request);
    }

    /** @return array<string, mixed> 走 AdminAuth 中间件，code=0 表示放行 */
    private function adminAuth(string $accessToken): array
    {
        $request = new Request("GET /admin/v1/user HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer {$accessToken}\r\n\r\n");

        $response = (new AdminAuth())->process($request, static fn(): Response => json(['code' => 0, 'data' => []]));

        return json_decode($response->rawBody(), true);
    }

    /** @return array{0: string, 1: array<int, array{x: int, y: int}>} 验证码 key 与按存储答案构造的正确点击 */
    private function solveCaptcha(): array
    {
        $storage = StorageFactory::create(PosterConfig::get('captcha.storage'));
        $result  = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $storage->get($result['key'])['targets'] ?? [];
        $this->assertNotEmpty($targets, '应能读到本次验证码的答案（生成与校验同一份存储）');

        return [
            (string) $result['key'],
            array_map(static fn(array $t): array => ['x' => (int) $t['x'], 'y' => (int) $t['y']], $targets),
        ];
    }

    private function createAdmin(int $status, bool $deleted = false): AdminUser
    {
        $username = $this->probeUsername('probe');

        try {
            $user = new AdminUser();
            $user->id = SnowflakeService::generate();
            $user->username = $username;
            $user->password = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
            $user->real_name = 'revocation_probe';
            $user->email = '';
            $user->phone = '';
            $user->status = $status;
            $user->save();

            if ($deleted) {
                $user->delete();
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        return $user;
    }

    private function probeUsername(string $prefix): string
    {
        $username = "revocation_{$prefix}_" . bin2hex(random_bytes(6));
        $this->probeUsernames[] = $username;

        return $username;
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

    /** 与控制器同一份配置/密钥铸造 refresh token（sub 不存在于库中，避免污染 last_login_at） */
    private function mintRefreshToken(int $expire = 0): string
    {
        return $this->jwt()->encode(['sub' => self::SUB, 'token_type' => 'refresh'], $expire);
    }

    private function jwt(): JWT
    {
        $jwt = (new ReflectionMethod(AuthController::class, 'getJWT'))->invoke(null);
        assert($jwt instanceof JWT);

        return $jwt;
    }
}
