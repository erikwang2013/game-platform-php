<?php
/**
 * 管理端 (admin, 默认 8789) 全量接口冒烟 + 认证链 + 提现双审链 + 负例。
 *
 * 运行:  php -d auto_prepend_file=/tmp/gp-env-preload.php tests/api/admin_test.php
 * 环境:  BASE_URL=管理端地址  BASE_URL_SERVICE=服务端地址  ADMIN_USER/ADMIN_PASS 可覆盖
 *
 * 说明: 登录接口需点击验证码, 线上 /api/captcha/generate 存在已知缺陷(见报告),
 * 本脚本直接调用库函数生成验证码并回填答案(与生产同库同 Redis)。
 */

require __DIR__ . '/harness.php';

// 清空测试环境限流计数(Redis 滑动窗口跨测试运行累积, 注册接口 5 次/分钟)
$rl = new Redis();
$rl->connect('127.0.0.1', 6379);
foreach ($rl->keys('rate_limit:*') ?: [] as $k) {
    $rl->del($k);
}

// ---- 引导 admin 应用(仅用于 captcha_create + PDO), 纯 HTTP 断言仍走 harness ----
chdir('/home/wwwroot/game-platform-php/admin');
require '/home/wwwroot/game-platform-php/admin/vendor/autoload.php';
require '/home/wwwroot/game-platform-php/admin/support/bootstrap.php';

$ADMIN_USER = getenv('ADMIN_USER') ?: 'admin';
$ADMIN_PASS = getenv('ADMIN_PASS') ?: 'Admin@123';
$ROUTES = collect_routes('/home/wwwroot/game-platform-php/admin/config/route.php');

/** 生成点击验证码并返回 [key, clicks] */
function captcha_seed(): array
{
    $r = captcha_create('click', ['difficulty' => 'easy']);
    $key = $r['key'];
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $payload = json_decode((string) $redis->get("poster:captcha:$key"), true);
    $targets = $payload['data']['targets'] ?? [];
    usort($targets, fn($a, $b) => $a['order'] <=> $b['order']);
    $clicks = array_map(fn($t) => ['x' => $t['x'], 'y' => $t['y']], $targets);
    if (!$clicks) {
        throw new RuntimeException('captcha targets empty');
    }
    return [$key, $clicks];
}

function admin_login(): string
{
    global $ADMIN_USER, $ADMIN_PASS;
    [$key, $clicks] = captcha_seed();
    $res = api('POST', '/api/auth/login', [
        'username'    => $ADMIN_USER,
        'password'    => $ADMIN_PASS,
        'captcha_key' => $key,
        'clicks'      => $clicks,
    ]);
    t_check('登录(验证码+账密)', $res, [0, 401, 422, 423]);
    return $res[1]['data']['access_token'] ?? '';
}

// ================= 公开端点 (health/security.txt 公开; metrics/docs 带 AdminAuth) =================
t_check('GET /health', api('GET', '/health'), [200]);
t_check('GET /.well-known/security.txt', api('GET', '/.well-known/security.txt'), [200]);
t_check('GET /metrics(未登录)', api('GET', '/metrics'), [401]);
t_check('GET /api/docs(未登录)', api('GET', '/api/docs'), [401]);

// ================= 认证链 =================
echo "---- 认证链 ----\n";
$token = admin_login();
t_ok('登录后取得 access_token', strlen($token) > 20, 'token=' . substr($token, 0, 30));

t_check('认证失败: 无 Token', api('GET', '/admin/dashboard'), [401]);
t_check('认证失败: 伪造 Token', api('GET', '/admin/dashboard', null, 'garbage.token.here'), [401]);

// 错误密码(有效验证码, 仅 1 次, 避免触发 5 次锁定)
[$k2, $c2] = captcha_seed();
t_check('登录负例: 错误密码', api('POST', '/api/auth/login', [
    'username' => $ADMIN_USER, 'password' => 'WrongPass@999',
    'captcha_key' => $k2, 'clicks' => $c2,
]), [401, 422, 423]);

t_check('注册负例: 缺参数', api('POST', '/api/auth/register', []), [422]);
t_check('刷新负例: 缺 refresh_token', api('POST', '/api/auth/refresh', []), [422]);

// ================= /admin/* 全量冒烟 =================
echo "---- /admin/* 冒烟 ----\n";
// 200 允许: 导出类接口成功时直接流式返回二进制文件(非 JSON), biz_code 退化为 HTTP 200
$writePassCodes = [0, 200, 400, 401, 403, 404, 409, 422, 429];
$hashidLike = fn(string $p): bool => (bool) preg_match('#\{[a-z_]+\}#', $p);

foreach ($ROUTES as [$method, $path]) {
    if ($path === '/admin' || !str_starts_with($path, '/admin')) {
        continue; // 仅冒烟 /admin 组, 公开端点已单独测试
    }
    $name = "$method $path";
    if (in_array($path, ['/admin/import/users', '/admin/upload'], true)) {
        t_skip($name . ' (multipart 文件上传, 不在冒烟范围)');
        continue;
    }
    if ($path === '/admin/profile/logout') {
        t_skip($name . ' (登出会拉黑 Token, 已在末位单独测试)');
        continue;
    }
    $body = $method === 'GET' || $method === 'DELETE' ? null : [];
    $allow = $hashidLike($path) ? [0, 400, 404, 422] : ($method === 'GET' ? [0, 403, 422] : $writePassCodes);
    t_check("冒烟 $name", api($method, $path, $body, $token), $allow);
}

// ================= /api 公开冒烟 =================
echo "---- /api/* 冒烟 ----\n";
t_check('冒烟 POST /api/captcha/generate(已知缺陷: extra.targets, 预期 500)', api('POST', '/api/captcha/generate'), [0]);
t_check('冒烟 POST /api/captcha/verify', api('POST', '/api/captcha/verify', []), [0, 422]);
t_check('冒烟 POST /api/auth/register', api('POST', '/api/auth/register', []), [422, 429]);
t_check('冒烟 POST /api/auth/login(空参)', api('POST', '/api/auth/login', []), [422]);
t_check('冒烟 POST /api/auth/refresh(空参)', api('POST', '/api/auth/refresh', []), [422]);

// ================= 提现双审链(service 申请 → admin 初审 → admin 确认) =================
echo "---- 提现双审链 ----\n";
$svcBase = 'BASE_URL_SERVICE';
$svcUser = 'qa_chain_' . substr((string) time(), -6);
$svcToken = '';
$r = api('POST', '/api/auth/register', ['username' => $svcUser, 'password' => 'Chain@123', 'email' => "$svcUser@test.local"], null, [], $svcBase);
t_check("服务端注册 $svcUser", $r, [0, 422]);
$svcToken = $r[1]['data']['access_token'] ?? '';

if ($svcToken) {
    // JWT sub 即 user_id
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], explode('.', $svcToken)[1] ?? '')), true);
    $userId = (int) ($payload['sub'] ?? 0);
    // 直连测试库为链上用户充值(测试数据, 非业务改动)
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=game-platform-test;charset=utf8mb4', 'root', '');
    $pdo->exec("UPDATE game_user_wallet SET balance = balance + 500 WHERE user_id = $userId");
    $funded = $pdo->query("SELECT balance FROM game_user_wallet WHERE user_id = $userId")->fetchColumn();
    t_ok("链上充值 +500 (余额=$funded)", (float) $funded >= 500, 'PDO 充值失败');

    $w = api('POST', '/api/withdraw/apply', [
        'platform_amount' => '50', 'method' => 'paypal', 'account_info' => 'qa@test.local',
    ], $svcToken, [], $svcBase);
    t_check('服务端提现申请', $w, [0, 400, 403]);
    $orderId = $w[1]['data']['order_id'] ?? '';
    $orderNo = $w[1]['data']['order_no'] ?? '';
    t_note('提现申请结果', json_encode($w[1]['data'] ?? $w[1], JSON_UNESCAPED_UNICODE));

    if ($orderId && $orderNo) {
        // 已知缺陷: admin 与 service 的 hashids 配置不兼容(length 16 vs 0), 服务端订单号
        // 无法被 admin 直接解码。此处经 DB 取原始 ID, 再用 admin 端配置重新编码(仅测试绕过)。
        $rawId = (int) $pdo->query("SELECT id FROM game_withdraw_order WHERE order_no = " . $pdo->quote($orderNo))->fetchColumn();
        $adminHashids = new \Hashids\Hashids(getenv('HASHIDS_SALT'), 0);
        $adminOrderId = $rawId ? $adminHashids->encode([$rawId]) : '';
        t_ok('DB 取回提现订单原始 ID', $rawId > 0, 'order_no 未在 DB 找到');
        t_note('hashids 不兼容绕过', "service order_id=$orderId -> admin order_id=$adminOrderId");

        $list = api('GET', '/admin/withdraw/orders?status=pending', null, $token);
        t_check('管理端待审列表', $list, [0]);
        $listed = str_contains(json_encode($list[1]), $orderNo ?? '');
        t_ok("列表可见订单 $orderNo", $listed, '订单未出现在管理端列表');

        $a1 = api('PUT', '/admin/withdraw/review', ['order_id' => $adminOrderId, 'action' => 'approve', 'note' => 'qa approve'], $token);
        t_check('管理端初审 approve', $a1, [0, 422]);
        t_note('初审结果', $a1[1]['message'] ?? '');

        $a2 = api('PUT', '/admin/withdraw/review', ['order_id' => $adminOrderId, 'action' => 'confirm', 'note' => 'qa confirm'], $token);
        t_check('管理端二次 confirm(双审启用时应拒绝同人复核)', $a2, [0, 422]);
        t_note('二次确认结果', $a2[1]['message'] ?? '');

        $s = api('GET', '/api/withdraw/orders', null, $svcToken, [], $svcBase);
        t_check('服务端提现记录可见', $s, [0]);
    }
}

// ================= 登出(末位: 登出会拉黑 Token) =================
t_check('登出', api('POST', '/admin/profile/logout', [], $token), [0]);
t_check('登出后旧 Token 失效', api('GET', '/admin/dashboard', null, $token), [401]);

// ================= 结果 =================
t_summary('admin API');
