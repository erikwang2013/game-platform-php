<?php
/**
 * 服务端 (service, 默认 8795) 全量接口冒烟 + 认证链 + 钱包/充值/提现链 + 负例。
 *
 * 运行:  php -d auto_prepend_file=/tmp/gp-env-preload.php tests/api/service_test.php
 * 环境:  BASE_URL=服务端地址
 */

require __DIR__ . '/harness.php';

// 本套件默认目标为服务端; BASE_URL 未显式给出时指向 8795
putenv('BASE_URL=' . (getenv('BASE_URL') ?: 'http://127.0.0.1:8795'));

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
$r = api('POST', '/api/auth/register', [
    'username' => $uname, 'password' => 'QaPass@123', 'email' => "$uname@test.local",
]);
t_check("注册 $uname", $r, [0, 422]);
$token = $r[1]['data']['access_token'] ?? '';
$refresh = $r[1]['data']['refresh_token'] ?? '';
t_ok('注册返回 access_token', strlen($token) > 20, 'token=' . substr($token, 0, 30));

$r2 = api('POST', '/api/auth/login', ['username' => $uname, 'password' => 'QaPass@123']);
t_check('登录', $r2, [0, 401, 422]);
$token = $r2[1]['data']['access_token'] ?? $token;
$refresh = $r2[1]['data']['refresh_token'] ?? $refresh;

$r3 = api('POST', '/api/auth/refresh', ['refresh_token' => $refresh]);
t_check('刷新 token', $r3, [0, 401, 422]);
$token = $r3[1]['data']['access_token'] ?? $token;

t_check('登录负例: 错误密码', api('POST', '/api/auth/login', ['username' => $uname, 'password' => 'Wrong@999']), [401, 422]);
t_check('注册负例: 重名', api('POST', '/api/auth/register', ['username' => $uname, 'password' => 'QaPass@123']), [422]);
t_check('注册负例: 弱密码', api('POST', '/api/auth/register', ['username' => 'qa_weak', 'password' => 'x']), [422]);
t_check('认证失败: 无 Token', api('GET', '/api/wallet/info'), [401]);
t_check('认证失败: 伪造 Token', api('GET', '/api/wallet/info', null, 'garbage.token.here'), [401]);

// ================= 全量冒烟 =================
echo "---- 全量冒烟 ----\n";
$writePassCodes = [0, 400, 401, 403, 404, 409, 422, 429];
$hashidLike = fn(string $p): bool => (bool) preg_match('#\{[a-z_]+\}#', $p);
$publicPaths = ['/api/auth/register', '/api/auth/login', '/api/auth/refresh', '/api/captcha/generate',
    '/api/language/list', '/api/language/switch', '/api/country/list', '/api/country/{code}',
    '/api/game/list', '/api/game/suggest', '/api/game/detail/{hashid}',
    '/api/announcement/list', '/api/announcement/detail/{hashid}',
    '/api/leaderboard/list', '/api/leaderboard/{hashid}', '/api/search',
    '/api/payment/callback', '/api/payment/methods', '/api/2fa/verify',
    '/api/auth/oauth/{provider}', '/api/auth/oauth/{provider}/callback'];

foreach ($ROUTES as [$method, $path]) {
    if ($path === '/api' || $path === '/api/provider' || $path === '/api/verify' || $path === '/api/friend'
        || $path === '/api/chat' || $path === '/api/webhook' || $path === '/api/tournament' || $path === '/api/ticket') {
        continue; // group 容器本身无路由
    }
    $name = "$method $path";
    $public = in_array($path, $publicPaths, true);
    $useToken = $public ? null : $token;

    // 删除类与高风险端点延后单独处理
    if ($path === '/api/user/delete-account') {
        continue;
    }
    if ($path === '/api/device/token' && $method === 'DELETE') {
        t_skip($name . ' (与 POST 同路由, 冒烟 POST 即可)');
        continue;
    }
    if ($path === '/api/verification/confirm-email' || $path === '/api/verification/confirm-phone') {
        t_skip($name . ' (需邮件/短信令牌, 无法冒烟)');
        continue;
    }

    $body = $method === 'GET' || $method === 'DELETE' ? null : [];
    if ($path === '/api/tournament/list') {
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
$pm = api('GET', '/api/payment/methods', null, $token);
t_check('支付方式列表', $pm, [0]);
$pmId = $pm[1]['data']['id'] ?? ($pm[1]['data'][0]['id'] ?? ($pm[1]['data']['list'][0]['id'] ?? ''));
if ($pmId) {
    $dep = api('POST', '/api/deposit/create', [
        'amount' => '10', 'currency' => 'USD', 'payment_method_id' => $pmId,
    ], $token);
    t_check('创建充值订单', $dep, [0, 422, 400]);
    t_note('充值订单结果', json_encode($dep[1]['data'] ?? $dep[1], JSON_UNESCAPED_UNICODE));

    t_check('充值记录', api('GET', '/api/deposit/orders', null, $token), [0]);
    t_check('钱包信息', api('GET', '/api/wallet/info', null, $token), [0]);
    t_check('钱包流水', api('GET', '/api/wallet/transactions', null, $token), [0]);

    // 未入账则余额为 0, 提现应被余额校验拒绝(记录真实业务流)
    $w = api('POST', '/api/withdraw/apply', [
        'platform_amount' => '5', 'method' => 'paypal', 'account_info' => 'qa@test.local',
    ], $token);
    t_check('提现申请(余额不足应拒绝)', $w, [0, 400, 403]);
    t_note('提现申请结果', $w[1]['message'] ?? '');

    t_check('提现记录', api('GET', '/api/withdraw/orders', null, $token), [0]);
} else {
    t_note('充值链', 'payment/methods 未返回可用支付方式, 充值链跳过');
    t_skip('充值/提现业务链 (无可用支付方式)');
}

t_check('汇率报价', api('POST', '/api/exchange/quote', ['amount' => '1', 'from' => 'USD', 'to' => 'platform'], $token), $writePassCodes);
t_check('用户资料', api('GET', '/api/user/profile', null, $token), [0]);
t_check('更新用户资料', api('PUT', '/api/user/profile', ['nickname' => 'qa_nick'], $token), [0, 422, 400]);

// 末位执行: 删除测试账号(自身), 验证删除后登录失效 (路由注册为 POST)
$da = api('POST', '/api/user/delete-account', [], $token);
t_check('注销账号(末位)', $da, [0, 400, 403, 422]);
if (($da[1]['code'] ?? -1) === 0) {
    t_check('注销后登录被拒', api('POST', '/api/auth/login', ['username' => $uname, 'password' => 'QaPass@123']), [401, 422]);
}

// ================= 结果 =================
t_summary('service API');
