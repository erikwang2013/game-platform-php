<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\GameController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use FastRoute\Dispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Middleware;
use Webman\Route;

/**
 * M1 服务端令牌签发端的端到端验证（真路由表 + 真中间件链 + 真控制器 + 真库）。
 *
 * 走的是框架自己的分发路径：Route::dispatch 拿到 ['callback','route']，再用 App::getCallback 配出
 * 与线上一致的中间件链（全局 TraceId/Cors/SecurityFilter/RateLimit/Language + 路由的 ProviderAuth/SdkSessionAuth）。
 * 因此这里断言的是「请求真的能被中间件放行并落到控制器」，不是直接 new 控制器的白盒调用。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
class ProviderSessionTokenIntegrationTest extends TestCase
{
    private const START_BALANCE = '1000';
    private const BET = '10';
    private const WIN = '100';
    private const SESSION_ID = 'M1-IT-SESSION';
    private const ROUND_ID = 'M1-IT-ROUND';
    private const ISSUER_PATH = '/api/provider/session-token';

    private static bool $booted = false;

    private int $userId = 0;
    private int $gameId = 0;
    private int $currencyId = 0;
    private int $thirdPartyGameId = 0;
    private int $emptySecretGameId = 0;
    private string $secret = '';
    private string $otherSecret = 'm1-it-other-secret';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // route.php 的装载是「清空静态状态 + require_once」，调第二次得到 0 条路由的假象 ⇒ 全进程只装一次
        if (!Route::getRoutes()) {
            Route::load([dirname(__DIR__) . '/config']);
        }
        // 全局中间件链由 webman 启动流程装配，PHPUnit 下缺这层引导（与 GameRouteTest 同一处缺口）
        Middleware::load(config('middleware', []), '');

        // App::$requestClass 由 webman 启动时的 new App(support\Request::class, ...) 注入，PHPUnit 下是空串。
        // 空串会让框架认不出控制器签名里的 Request $request，转而去容器构造 support\Request('$buffer')
        // ⇒ 每个落到控制器的请求都 400 "Missing input parameter buffer"。这里补上同一处注入。
        (new \ReflectionClass(App::class))->setStaticPropertyValue('requestClass', Request::class);

        self::bootTargetDatabase();
        // GameController::session() 的 game_id 走 hashids 容器绑定，PHPUnit 下需手动启动插件引导
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过端到端测试）：' . $e->getMessage());
        }

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 只删本用例造的行（snowflake ID 唯一），不 TRUNCATE、不碰他人数据
        foreach (['user_game_wallet', 'game_play_log', 'transaction'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        Db::table('game_currency')->where('id', $this->currencyId)->delete();
        foreach ([$this->gameId, $this->thirdPartyGameId, $this->emptySecretGameId] as $gameId) {
            Db::table('game')->where('id', $gameId)->delete();
        }
        Db::table('user')->where('id', $this->userId)->delete();
    }

    /**
     * M1 验收点：持 api_secret 的一方按协议签一次请求即换到 role=server 令牌，
     * 该令牌能让 bet/settle 两个写端点真的动账，balance 也照常可读。
     */
    #[Test]
    public function serverTokenUnlocksSdkWriteEndpoints(): void
    {
        $issued = $this->issueServerToken();
        $this->assertSame(0, $issued['code'], '签发端应成功：' . json_encode($issued));
        $token = (string) $issued['data']['token'];

        // 读端点：同一枚令牌照常可读
        $balance = $this->sdk('balance', $token, ['currency_id' => $this->currencyId]);
        $this->assertSame(0, $balance['code'], '服务端令牌读余额应成功：' . json_encode($balance));
        $this->assertSame(0, bccomp((string) $balance['data']['balance'], self::START_BALANCE, 8));

        // 写端点 bet：真扣钱
        $bet = $this->sdk('bet', $token, [
            'currency_id' => $this->currencyId,
            'session_id'  => self::SESSION_ID,
            'amount'      => self::BET,
            'round_id'    => self::ROUND_ID,
        ]);
        $this->assertSame(0, $bet['code'], '服务端令牌下注应成功（M1 的验收点）：' . json_encode($bet));
        $this->assertSame(0, bccomp($this->balance(), '990', 8), '下注后余额应为 1000 − 10：' . $this->balance());

        // 写端点 settle：真派奖（上限为同 round 投注额 × 100）
        $settle = $this->sdk('settle', $token, [
            'currency_id' => $this->currencyId,
            'session_id'  => self::SESSION_ID,
            'amount'      => self::WIN,
            'round_id'    => self::ROUND_ID,
        ]);
        $this->assertSame(0, $settle['code'], '服务端令牌结算应成功：' . json_encode($settle));
        $this->assertSame(0, bccomp($this->balance(), '1090', 8), '结算后余额应为 990 + 100：' . $this->balance());
    }

    /**
     * 签发出来的 claims 形状：role/game_id/user_id/exp 四项缺一不可，且都取自**验签通过的那份上下文**。
     *
     * 单独一个用例（而不是塞进上面的流程用例）：PHPUnit 的断言失败会终止当次用例，
     * 混在一起时「claims 写歪」会盖住它的后果（写端点 401），两个缺陷线索只剩一条。
     */
    #[Test]
    public function issuedTokenClaimsBindGameUserRoleAndTtl(): void
    {
        $issued = $this->issueServerToken();
        $this->assertSame(0, $issued['code'], '签发端应成功：' . json_encode($issued));

        // 一律用 ?? null 取键：缺键只判红这一条，别让 PHP warning 把用例提前掐断
        $claims = $this->claimsOf((string) $issued['data']['token']);
        $this->assertSame('server', $claims['role'] ?? null, '签发端的 role 不是 server');
        $this->assertSame($this->userId, $claims['user_id'] ?? null, 'claims 未绑定签发时指定的 user_id（缺它 SdkSessionAuth 判 401 Invalid token claims）');
        $this->assertSame($this->gameId, $claims['game_id'] ?? null, 'claims 的 game_id 不是签发端验签通过的那个 game');
        $this->assertSame(120, $issued['data']['expires_in'], '写令牌 TTL 应短于读令牌的 300s');
        $this->assertLessThan(300, ($claims['exp'] ?? 0) - time(), 'claims.exp 与读令牌同样长（或更长）');
    }

    /**
     * M0 未被削弱：同一游戏、同样合法的 read 令牌调写端点仍 403，且钱一分没动。
     * 读令牌取自已发布的真实签发端（GameController::session），不是手工拼的 claims。
     */
    #[Test]
    public function readTokenStillCannotWrite(): void
    {
        $readToken = $this->issueReadToken();
        $this->assertSame('read', $this->claimsOf($readToken)['role'], '读端点签出的令牌不再是 read :: M0 的源判据被绕过');

        $bet = $this->sdk('bet', $readToken, [
            'currency_id' => $this->currencyId,
            'session_id'  => self::SESSION_ID,
            'amount'      => self::BET,
            'round_id'    => self::ROUND_ID,
        ]);
        $this->assertSame(403, $bet['code'], 'read 令牌竟能下注 :: M0 的写闸被削弱：' . json_encode($bet));
        $this->assertSame(0, bccomp($this->balance(), self::START_BALANCE, 8), '被闸挡下的请求不得动账：' . $this->balance());

        // 同一枚读令牌调读端点仍可用（写闸没有误伤读路径）
        $balance = $this->sdk('balance', $readToken, ['currency_id' => $this->currencyId]);
        $this->assertSame(0, $balance['code'], 'read 令牌读余额应成功：' . json_encode($balance));
    }

    /** 换令牌的门槛：无签名 / 错签名 / 空 api_secret 的游戏，一律换不到令牌 */
    #[Test]
    public function issuerRejectsUnsignedAndWronglySignedRequests(): void
    {
        $unsigned = $this->post(self::ISSUER_PATH, ['user_id' => $this->userId]);
        $this->assertSame(401, $unsigned['code'], '无签名竟能换令牌：' . json_encode($unsigned));

        $wrongSign = $this->post(
            self::ISSUER_PATH,
            ['user_id' => $this->userId],
            $this->signHeaders(self::ISSUER_PATH, ['user_id' => $this->userId], 'not-the-secret')
        );
        $this->assertSame(401, $wrongSign['code'], '错签名竟能换令牌：' . json_encode($wrongSign));

        // 空 api_secret 的游戏：hash_hmac(..., '') 人人可算，必须 fail-closed（与 M0 的空密钥闸同一条）
        $emptySecret = $this->post(
            self::ISSUER_PATH,
            ['user_id' => $this->userId],
            $this->signHeaders(self::ISSUER_PATH, ['user_id' => $this->userId], '', $this->emptySecretGameId)
        );
        $this->assertSame(401, $emptySecret['code'], 'api_secret 为空的游戏竟能换令牌（签名形同虚设）：' . json_encode($emptySecret));
    }

    /**
     * 令牌与 game 绑定：claims 里的 game_id 决定用哪把密钥验签，密钥不对即 401。
     * 所以「拿到 A 的密钥」换不出「B 的写令牌」。
     */
    #[Test]
    public function tokenIsBoundToTheGameWhoseSecretSignedIt(): void
    {
        $forged = $this->craftToken($this->thirdPartyGameId, $this->userId, 'server', $this->secret);
        $r = $this->sdk('bet', $forged, [
            'currency_id' => $this->currencyId,
            'session_id'  => self::SESSION_ID,
            'amount'      => self::BET,
            'round_id'    => self::ROUND_ID,
        ]);
        $this->assertSame(401, $r['code'], '用 A 的密钥签 B 的 claims 竟被受理（game 绑定失效）：' . json_encode($r));

        $swapped = $this->craftToken($this->gameId, $this->userId, 'server', $this->otherSecret);
        $r2 = $this->sdk('balance', $swapped, ['currency_id' => $this->currencyId]);
        $this->assertSame(401, $r2['code'], '用别的密钥签本 game 的 claims 竟被受理：' . json_encode($r2));
    }

    /**
     * claims 必须带 user_id：SdkSessionAuth 把缺 user_id 的 claims 判成无效令牌（401 Invalid token claims），
     * 请求根本到不了控制器。签名用的是真密钥，所以这里排除的是「签名对但形状不对」这一路 ——
     * 也正因为这条既有约束，M1 的签发端必须把 user_id 写进 claims（而不是像 /api/provider/* 那样只认请求体）。
     */
    #[Test]
    public function serverTokenWithoutUserIdIsRejectedBeforeController(): void
    {
        $noUserId = $this->craftToken($this->gameId, null, 'server', $this->secret);
        $r = $this->sdk('bet', $noUserId, [
            'currency_id' => $this->currencyId,
            'session_id'  => self::SESSION_ID,
            'amount'      => self::BET,
            'round_id'    => self::ROUND_ID,
            'user_id'     => $this->userId,
        ]);

        $this->assertSame(401, $r['code'], '缺 user_id 的服务端令牌竟被受理：' . json_encode($r));
        $this->assertSame('Invalid token claims', $r['message'], '拒绝原因不是 claims 形状闸 :: 令牌在更后面才被挡下，写路径的 user_id 归属不明');
        $this->assertSame(0, bccomp($this->balance(), self::START_BALANCE, 8), '被闸挡下的请求不得动账：' . $this->balance());
    }

    /** 过期令牌不再可用（TTL 真的在约束暴露窗口）；并且 role 只由签发端写死，请求体塞不进去 */
    #[Test]
    public function expiredTokenIsRejectedAndRoleComesFromIssuerOnly(): void
    {
        $expired = $this->craftToken($this->gameId, $this->userId, 'server', $this->secret, time() - 1);
        $r = $this->sdk('balance', $expired, ['currency_id' => $this->currencyId]);
        $this->assertSame(401, $r['code'], '过期令牌仍被受理 :: TTL 形同虚设：' . json_encode($r));

        // 请求体里塞 role 不影响签发结果：role 由签发端写死
        $issued = $this->issueServerToken(['role' => 'read', 'admin' => true]);
        $this->assertSame(0, $issued['code'], '签发应成功：' . json_encode($issued));
        $claims = $this->claimsOf((string) $issued['data']['token']);
        $this->assertSame('server', $claims['role'], '请求体里的 role 覆盖了签发端的角色 :: 角色不再是签发端说了算');
        $this->assertArrayNotHasKey('admin', $claims, '请求体的多余字段混进了 claims');
    }

    /** 签发端的入参闸：user_id 必填且有下限；非 self/embedded 的游戏不签 SDK 令牌 */
    #[Test]
    public function issuerValidatesUserIdAndGameType(): void
    {
        $missing = $this->post(self::ISSUER_PATH, [], $this->signHeaders(self::ISSUER_PATH, []));
        $this->assertSame(422, $missing['code'], '不传 user_id 竟能换令牌：' . json_encode($missing));

        $zero = $this->post(self::ISSUER_PATH, ['user_id' => 0], $this->signHeaders(self::ISSUER_PATH, ['user_id' => 0]));
        $this->assertSame(422, $zero['code'], 'user_id=0 竟能换令牌：' . json_encode($zero));

        $thirdParty = $this->post(
            self::ISSUER_PATH,
            ['user_id' => $this->userId],
            $this->signHeaders(self::ISSUER_PATH, ['user_id' => $this->userId], $this->otherSecret, $this->thirdPartyGameId)
        );
        $this->assertSame(403, $thirdParty['code'], '第三方游戏也拿到了 SDK 令牌（SDK 端点必然按类型拒收它）：' . json_encode($thirdParty));
    }

    // ------------------------------------------------------------------
    // 夹具 / 收发 / 断言帮手
    // ------------------------------------------------------------------

    private function seedFixtures(): void
    {
        $this->userId           = SnowflakeService::generate();
        $this->gameId           = SnowflakeService::generate();
        $this->currencyId       = SnowflakeService::generate();
        $this->thirdPartyGameId = SnowflakeService::generate();
        $this->emptySecretGameId = SnowflakeService::generate();
        $this->secret           = 'm1-it-secret-' . $this->gameId;
        $this->otherSecret      = 'm1-it-third-party-secret';

        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'm1_it_' . $this->userId,
            'password' => 'not-a-real-hash', // 本用例不校验口令，仅满足 NOT NULL
        ]);
        Db::table('game')->insert([
            ['id' => $this->gameId, 'name' => 'M1 IT Game', 'slug' => 'm1-it-' . $this->gameId, 'type' => 'self', 'status' => 1, 'api_secret' => $this->secret],
            ['id' => $this->thirdPartyGameId, 'name' => 'M1 IT Third Party', 'slug' => 'm1-it-tp-' . $this->thirdPartyGameId, 'type' => 'third_party', 'status' => 1, 'api_secret' => $this->otherSecret],
            ['id' => $this->emptySecretGameId, 'name' => 'M1 IT No Secret', 'slug' => 'm1-it-ns-' . $this->emptySecretGameId, 'type' => 'self', 'status' => 1, 'api_secret' => ''],
        ]);
        Db::table('game_currency')->insert([
            'id'      => $this->currencyId,
            'game_id' => $this->gameId,
            'name'    => 'M1 IT Coin',
            'symbol'  => 'M1C',
        ]);
        Db::table('user_game_wallet')->insert([
            'id'          => SnowflakeService::generate(),
            'user_id'     => $this->userId,
            'game_id'     => $this->gameId,
            'currency_id' => $this->currencyId,
            'balance'     => self::START_BALANCE,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function issueServerToken(array $payload = []): array
    {
        $payload = $payload + ['user_id' => $this->userId];

        return $this->post(self::ISSUER_PATH, $payload, $this->signHeaders(self::ISSUER_PATH, $payload));
    }

    /** 读令牌取自真实签发端（GameController::session，挂在 UserAuth 下，故直连控制器并手工注入 userId） */
    private function issueReadToken(): string
    {
        $request = new Request("GET /api/v1/game/session HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setGet(['game_id' => HashidsService::encode($this->gameId)]);
        $request->userId = $this->userId;

        $body = json_decode((string) (new GameController())->session($request)->rawBody(), true);
        $this->assertSame(0, $body['code'], '读令牌签发失败：' . json_encode($body));

        return (string) $body['data']['token'];
    }

    /** @param array<string, mixed> $payload */
    private function sdk(string $action, string $token, array $payload): array
    {
        return $this->post('/api/game/' . $action, $payload, ['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * 按框架自身的分发路径发一次请求：Route::dispatch 拿到路由与回调，
     * 再用 App::getCallback 配出与线上一致的中间件链（全局 + 路由），最后落到控制器。
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, array $headers = []): array
    {
        $method = 'POST';
        $body = $payload === [] ? '' : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers += ['Content-Type' => 'application/json'];

        $info = Route::dispatch($method, $path);
        $this->assertSame(Dispatcher::FOUND, $info[0], "{$method} {$path} 未命中路由");

        $raw = "{$method} {$path} HTTP/1.1\r\nHost: localhost\r\n";
        foreach ($headers as $name => $value) {
            $raw .= "{$name}: {$value}\r\n";
        }
        $raw .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;

        $request = new Request($raw);
        $response = App::getCallback('', '', $info[1]['callback'], [], true, $info[1]['route'])($request);

        $decoded = json_decode((string) $response->rawBody(), true);
        $this->assertIsArray($decoded, "{$path} 未返回 JSON 信封：" . (string) $response->rawBody());

        return $decoded;
    }

    /**
     * 与 ProviderAuth::computeSignature 同一口径：HMAC-SHA256 覆盖 game_id:ts:METHOD:path:body。
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function signHeaders(string $path, array $payload, ?string $secret = null, ?int $gameId = null): array
    {
        $gameId ??= $this->gameId;
        $secret ??= $this->secret;
        $body = $payload === [] ? '' : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        return [
            'X-Game-Id'   => (string) $gameId,
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $gameId . ':' . $timestamp . ':POST:' . $path . ':' . $body, $secret),
        ];
    }

    /** 手工铸令牌（用于反证：签名/形状/有效期出错的令牌必须被拒） */
    private function craftToken(int $gameId, ?int $userId, string $role, string $secret, ?int $exp = null): string
    {
        $claims = ['game_id' => $gameId, 'role' => $role, 'exp' => $exp ?? time() + 120];
        if ($userId !== null) {
            $claims['user_id'] = $userId;
        }
        $payload = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');

        return $payload . '.' . hash_hmac('sha256', $payload, $secret);
    }

    /** @return array<string, mixed> */
    private function claimsOf(string $token): array
    {
        $payload = explode('.', $token, 2)[0];
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) {
            $payload .= str_repeat('=', 4 - $remainder);
        }
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertIsArray($claims, '令牌 payload 不是 JSON');

        return $claims;
    }

    private function balance(): string
    {
        return (string) Db::table('user_game_wallet')
            ->where('user_id', $this->userId)
            ->where('game_id', $this->gameId)
            ->where('currency_id', $this->currencyId)
            ->value('balance');
    }

    /**
     * 让 support\Db 指向测试库（与 ExchangeWalletIntegrationTest 同一处坑）：
     * tests/bootstrap.php 只把测试库配置写进一个局部数组；而 support\Db 首次被 autoload 时
     * 其文件尾部的 Webman\Database\Initializer::init(config('database')) 会再建一个 capsule 并 setAsGlobal，
     * 用的是【开发库】的 config('database')。所以必须先把这次一次性初始化烧掉，再自己 setAsGlobal。
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
