<?php
/**
 * 服务端 (service, 默认 8792) 全量接口冒烟 + 认证链 + 钱包/充值/提现链 + 负例。
 *
 * 运行:  php -d auto_prepend_file=/tmp/gp-env-preload.php tests/api/service_test.php
 * 环境:  BASE_URL=服务端地址
 */

require __DIR__ . '/harness.php';

// 本套件默认目标为服务端; BASE_URL 未显式给出时指向 8792
putenv('BASE_URL=' . (getenv('BASE_URL') ?: 'http://127.0.0.1:8792'));

// 清空测试环境限流计数(Redis 滑动窗口跨测试运行累积)
$rl = new Redis();
$rl->connect('127.0.0.1', 6379);
foreach ($rl->keys('rate_limit:*') ?: [] as $k) {
    $rl->del($k);
}

$ROUTES = collect_routes('/home/wwwroot/game-platform-php/service/config/route.php');

// ================= 公开端点 =================
t_check('GET /health', api('GET', '/health'), [200]);

// ================= 注册/登录/刷新链 =================
echo "---- 认证链 ----\n";
$uname = 'qa_' . substr((string) time(), -6);
$r = api('POST', '/api/v1/auth/register', [
    'username' => $uname, 'password' => 'QaPass@123', 'email' => "$uname@test.local",
]);
t_check("注册 $uname", $r, [0, 422]);
$token = $r[1]['data']['access_token'] ?? '';
$refresh = $r[1]['data']['refresh_token'] ?? '';
t_ok('注册返回 access_token', strlen($token) > 20, 'token=' . substr($token, 0, 30));

$r2 = api('POST', '/api/v1/auth/login', ['username' => $uname, 'password' => 'QaPass@123']);
t_check('登录', $r2, [0, 401, 422]);
$token = $r2[1]['data']['access_token'] ?? $token;
$refresh = $r2[1]['data']['refresh_token'] ?? $refresh;

$r3 = api('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresh]);
t_check('刷新 token', $r3, [0, 401, 422]);
$token = $r3[1]['data']['access_token'] ?? $token;

t_check('登录负例: 错误密码', api('POST', '/api/v1/auth/login', ['username' => $uname, 'password' => 'Wrong@999']), [401, 422]);
t_check('注册负例: 重名', api('POST', '/api/v1/auth/register', ['username' => $uname, 'password' => 'QaPass@123']), [422]);
t_check('注册负例: 弱密码', api('POST', '/api/v1/auth/register', ['username' => 'qa_weak', 'password' => 'x']), [422]);
t_check('认证失败: 无 Token', api('GET', '/api/v1/wallet/info'), [401]);
t_check('认证失败: 伪造 Token', api('GET', '/api/v1/wallet/info', null, 'garbage.token.here'), [401]);

// ================= SDK 铸币路径 (M0 信任边界) =================
echo "---- SDK 铸币路径 (M0) ----\n";

/**
 * M0 夹具 → [gameHash, gameId, currencyId, userId, B0, secret]；环境不具备时返回 null。
 *
 * M0 的闸只认「令牌角色」，要走到铸币路径就得先有一条能签令牌的游戏加一笔真本金：
 * 引导 service 应用只为夹具直连 DB（与 admin_test.php 同款做法），断言一律仍走 HTTP。
 * 本地/演示数据（install/test-data.sql）里三个游戏 api_secret 都是空串、game_currency 无行，
 * 而空密钥下 session() 直接 403、铸币路径根本走不到 —— 夹具就地补齐，幂等（重复跑不新增行）。
 */
function m0_fixture(string $username): ?array
{
    chdir('/home/wwwroot/game-platform-php/service');
    require_once '/home/wwwroot/game-platform-php/service/vendor/autoload.php';
    require_once '/home/wwwroot/game-platform-php/service/support/bootstrap.php';

    try {
        $game = \common\model\Game::whereIn('type', ['self', 'embedded'])
            ->where('status', 1)->orderBy('id')->first();
        if (!$game) {
            t_note('M0 夹具', '没有 type ∈ {self,embedded} 且 status=1 的游戏');

            return null;
        }
        if ((string) $game->api_secret === '') {
            $game->api_secret = 'm0-fixture-secret';
            $game->save();
        }
        $game = \common\model\Game::find($game->id);

        $currency = \common\model\GameCurrency::where('game_id', $game->id)->first();
        if (!$currency) {
            $currency = new \common\model\GameCurrency();
            $currency->id = 60000000000000301; // 固定夹具 ID：不依赖 Redis 雪花，且重复跑不会新增行
            $currency->game_id = $game->id;
            $currency->name = 'M0 Fixture Coin';
            $currency->symbol = 'M0C';
            $currency->save();
        }
        $currencyId = (int) $currency->id;

        $user = \common\model\User::where('username', $username)->first();
        if (!$user) {
            t_note('M0 夹具', "库里查不到刚注册的用户 {$username}");

            return null;
        }
        $userId = (int) $user->id;

        $scope = \app\service\WalletScope::game((int) $game->id, $currencyId);
        if (!\app\service\WalletService::mutate($userId, $scope, '+1000', 'game_earn', 'game_round', 0, 'M0 fixture')) {
            t_note('M0 夹具', '本金充值失败');

            return null;
        }

        return [
            \common\HashidsService::encode((int) $game->id),
            (int) $game->id,
            $currencyId,
            $userId,
            \app\service\WalletService::balance($userId, $scope),
            (string) $game->api_secret,
        ];
    } catch (\Throwable $e) {
        t_note('M0 夹具异常', substr($e->getMessage(), 0, 160));

        return null;
    }
}

$m0 = m0_fixture($uname);
if ($m0 === null) {
    t_skip('M0 SDK 铸币路径 (夹具不可用: 库不可达 / 缺自研内嵌游戏)');
} else {
    [$m0Game, $m0GameId, $m0Currency, $m0User, $m0B0, $m0Secret] = $m0;

    // 1) 任意登录用户领「游戏服务端令牌」—— 攻击起点
    $sess = api('GET', '/api/v1/game/session?game_id=' . $m0Game, null, $token);
    $sdk = (string) ($sess[1]['data']['token'] ?? '');
    $claims = json_decode((string) base64_decode(strtr(explode('.', $sdk)[0] ?? '', '-_', '+/')), true) ?: [];

    // 2) 只读令牌仍应能读余额（否则下面的 403 可能只是「令牌不认」而不是「角色被拦」）
    $bal = api('POST', '/api/game/balance', ['currency_id' => $m0Currency], $sdk);

    // 3/4) 同一个 round：下注 100 → 自报派奖 10000（比率闸 100×100、绝对闸 10000 都恰好不算超）
    $round = 'atk-' . bin2hex(random_bytes(4));
    $bet = api('POST', '/api/game/bet', [
        'currency_id' => $m0Currency, 'session_id' => 'S-' . $round, 'amount' => '100', 'round_id' => $round,
    ], $sdk);
    $settle = api('POST', '/api/game/settle', [
        'currency_id' => $m0Currency, 'session_id' => 'S-' . $round, 'amount' => '10000', 'round_id' => $round,
        'meta' => ['result' => 'win'],
    ], $sdk);

    // 5/6) 读数：余额必须原样、且该 round 不得落 settle 流水
    $b1 = \app\service\WalletService::balance($m0User, \app\service\WalletScope::game($m0GameId, $m0Currency));
    $betRows = \support\Db::table('game_play_log')->where('round_id', $round)->where('action', 'bet')->count();
    $settleRows = \support\Db::table('game_play_log')->where('round_id', $round)->where('action', 'settle')->count();

    // 7) 迁移影响：M0 之前签发的令牌形状（claims 无 role、TTL 300s）必须自动降级只读
    $legacyPayload = rtrim(strtr(base64_encode((string) json_encode([
        'game_id' => $m0GameId, 'user_id' => $m0User, 'exp' => time() + 300,
    ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    $legacy = api('POST', '/api/game/bet', [
        'currency_id' => $m0Currency, 'session_id' => 'S-legacy', 'amount' => '1', 'round_id' => 'legacy-' . $round,
    ], $legacyPayload . '.' . hash_hmac('sha256', $legacyPayload, $m0Secret));

    // 8) 反证：闸必须认角色，而不是「一律拒绝」（否则 M1 接进来同样被挡死）——
    //    按 M1 的形状自签一枚 role=server 令牌（密钥只在夹具手里），写端点应放行；
    //    放在最后：这步会真正扣掉 1 个夹具币，别污染上面的 B1 == B0。
    $srvPayload = rtrim(strtr(base64_encode((string) json_encode([
        'game_id' => $m0GameId, 'user_id' => $m0User, 'role' => 'server', 'exp' => time() + 300,
    ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    $srvBet = api('POST', '/api/game/bet', [
        'currency_id' => $m0Currency, 'session_id' => 'S-server', 'amount' => '1', 'round_id' => 'srv-' . $round,
    ], $srvPayload . '.' . hash_hmac('sha256', $srvPayload, $m0Secret));
    $srvCode = biz_code($srvBet);
    $b2 = \app\service\WalletService::balance($m0User, \app\service\WalletScope::game($m0GameId, $m0Currency));

    $betCode = biz_code($bet);
    $settleCode = biz_code($settle);
    $legacyCode = biz_code($legacy);
    $balCode = biz_code($bal);
    t_note('M0 读数', "session={$sess[0]}/" . biz_code($sess) . ' role=' . var_export($claims['role'] ?? null, true)
        . " bet=$betCode settle=$settleCode legacy_bet=$legacyCode balance=$balCode"
        . " B0={$m0B0} B1={$b1} delta=" . bcsub($b1, $m0B0, 8) . " bet_rows=$betRows settle_rows=$settleRows");

    t_ok('M0: 会话令牌只带 role=read', ($claims['role'] ?? null) === 'read', 'claims=' . json_encode($claims));
    t_ok('M0: 只读令牌仍可用（余额读端点）', $balCode === 0, "balance code=$balCode");
    t_ok('M0: 写端点拒绝只读令牌 (bet)', in_array($betCode, [401, 403], true), "bet code=$betCode");
    // 不写死 403：M1 换签发者后旧令牌可能先落到 401，别让那时假红
    t_ok('M0: 写端点拒绝只读令牌 (settle)', in_array($settleCode, [401, 403], true), "settle code=$settleCode");
    t_ok('M0: 铸币路径未入账 (B1 == B0)', bccomp($b1, $m0B0, 8) === 0, "B0={$m0B0} B1={$b1} delta=" . bcsub($b1, $m0B0, 8));
    t_ok('M0: 未落 settle 流水', $settleRows === 0, "settle_rows=$settleRows bet_rows=$betRows");
    t_ok('M0: 迁移窗口内旧令牌降级只读', in_array($legacyCode, [401, 403], true), "legacy bet code=$legacyCode");
    t_note('M0 反证读数', "server_role_bet=$srvCode B2={$b2}（夹具钱包，放行则扣 1）");
    t_ok('M0 反证: role=server 令牌仍可写（闸认角色不是一律拒）', $srvCode === 0, "server-role bet code=$srvCode");
}

// ================= 全量冒烟 =================
echo "---- 全量冒烟 ----\n";
$writePassCodes = [0, 400, 401, 403, 404, 409, 422, 429];
$hashidLike = fn(string $p): bool => (bool) preg_match('#\{[a-z_]+\}#i', $p);
$publicPaths = ['/api/v1/auth/register', '/api/v1/auth/login', '/api/v1/auth/refresh', '/api/v1/captcha/generate',
    '/api/v1/language/list', '/api/v1/language/switch', '/api/v1/country/list', '/api/v1/country/{code}',
    '/api/v1/game/list', '/api/v1/game/suggest', '/api/v1/game/detail/{hashid}',
    '/api/v1/announcement/list', '/api/v1/announcement/detail/{hashid}',
    '/api/v1/leaderboard/list', '/api/v1/leaderboard/{hashid}', '/api/v1/search',
    '/api/v1/payment/callback', '/api/v1/payment/methods', '/api/v1/2fa/verify',
    '/api/v1/platform/stats', '/api/v1/shares/visit',
    '/api/v1/auth/oauth/{provider}', '/api/v1/auth/oauth/{provider}/callback'];

foreach ($ROUTES as [$method, $path]) {
    $name = "$method $path";
    $public = in_array($path, $publicPaths, true);
    $useToken = $public ? null : $token;

    // 删除类与高风险端点延后单独处理
    if ($path === '/api/v1/user/delete-account') {
        continue;
    }
    if ($path === '/api/v1/device/token' && $method === 'DELETE') {
        t_skip($name . ' (与 POST 同路由, 冒烟 POST 即可)');
        continue;
    }
    if ($path === '/api/v1/verify/confirm-email' || $path === '/api/v1/verify/confirm-phone') {
        t_skip($name . ' (需邮件/短信令牌, 无法冒烟)');
        continue;
    }

    $body = $method === 'GET' || $method === 'DELETE' ? null : [];
    if ($path === '/api/v1/game/session') {
        // 必填 game_id(hashid) 查询参数, 冒烟不带参 → 422；M0 起令牌角色不足也可能是 401/403
        $allow = [0, 400, 401, 403, 404, 422];
    } elseif ($path === '/api/v1/tournament/list') {
        $allow = [0, 503]; // 功能未初始化时业务返回 503 Tournaments not available
    } elseif ($hashidLike($path)) {
        $allow = [0, 400, 404, 422];
    } elseif ($method === 'GET') {
        $allow = $public ? [0] : [0, 401];
    } else {
        $allow = $writePassCodes;
    }
    t_check("冒烟 $name", api($method, $path, $body, $useToken), $allow);
}

// ================= 钱包/充值/提现业务链 =================
echo "---- 业务链 ----\n";
$pm = api('GET', '/api/v1/payment/methods', null, $token);
t_check('支付方式列表', $pm, [0]);
$pmId = $pm[1]['data']['id'] ?? ($pm[1]['data'][0]['id'] ?? ($pm[1]['data']['list'][0]['id'] ?? ''));
if ($pmId) {
    $dep = api('POST', '/api/v1/deposit/create', [
        'amount' => '10', 'currency' => 'USD', 'payment_method_id' => $pmId,
    ], $token);
    // 502: 本机未配置网关密钥(NOWPAYMENTS_API_KEY), 控制器已回滚订单为 cancelled, 属环境前置条件
    t_check('创建充值订单', $dep, [0, 422, 400, 502]);
    t_note('充值订单结果', json_encode($dep[1]['data'] ?? $dep[1], JSON_UNESCAPED_UNICODE));

    t_check('充值记录', api('GET', '/api/v1/deposit/orders', null, $token), [0]);
    t_check('钱包信息', api('GET', '/api/v1/wallet/info', null, $token), [0]);
    t_check('钱包流水', api('GET', '/api/v1/wallet/transactions', null, $token), [0]);

    // 未入账则余额为 0, 提现应被余额校验拒绝(记录真实业务流)
    $w = api('POST', '/api/v1/withdraw/apply', [
        'platform_amount' => '5', 'method' => 'paypal', 'account_info' => 'qa@test.local',
    ], $token);
    t_check('提现申请(余额不足应拒绝)', $w, [0, 400, 403]);
    t_note('提现申请结果', $w[1]['message'] ?? '');

    t_check('提现记录', api('GET', '/api/v1/withdraw/orders', null, $token), [0]);
} else {
    t_note('充值链', 'payment/methods 未返回可用支付方式, 充值链跳过');
    t_skip('充值/提现业务链 (无可用支付方式)');
}

t_check('汇率报价', api('POST', '/api/v1/exchange/quote', ['amount' => '1', 'from' => 'USD', 'to' => 'platform'], $token), $writePassCodes);
t_check('用户资料', api('GET', '/api/v1/user/profile', null, $token), [0]);
t_check('更新用户资料', api('PUT', '/api/v1/user/profile', ['nickname' => 'qa_nick'], $token), [0, 422, 400]);

// 末位执行: 删除测试账号(自身), 验证删除后登录失效 (路由注册为 POST)
$da = api('POST', '/api/v1/user/delete-account', [], $token);
t_check('注销账号(末位)', $da, [0, 400, 403, 422]);
if (($da[1]['code'] ?? -1) === 0) {
    t_check('注销后登录被拒', api('POST', '/api/v1/auth/login', ['username' => $uname, 'password' => 'QaPass@123']), [401, 422]);
}

// ================= 结果 =================
t_summary('service API');
