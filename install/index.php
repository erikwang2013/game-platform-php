<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 一键安装向导 — 入口文件
 *
 * 使用方法: php -S 0.0.0.0:8888 -t install/
 */

require __DIR__ . '/Installer.php';

$installer = new Installer();

if ($installer->isInstalled()) {
    $lockData = json_decode(file_get_contents($installer->getLockFile()), true);
    sendResponse(200, renderPage('已安装', installedPage($lockData)));
}

$action = $_GET['action'] ?? 'step1';

switch ($action) {
    case 'step1': handleStep1($installer); break;
    case 'step2': handleStep2($installer); break;
    case 'step3': handleStep3($installer); break;
    case 'step4': handleStep4($installer); break;
    case 'test-db': handleTestDb($installer); break;
    default: handleStep1($installer);
}

function handleStep1(Installer $installer): void
{
    $results = $installer->checkEnvironment();
    $allPassed = $installer->allEnvChecksPassed();
    sendResponse(200, renderPage('环境检查', step1Page($results, $allPassed)));
}

function handleStep2(Installer $installer): void
{
    $results = $installer->checkEnvironment();
    if (!$installer->allEnvChecksPassed()) {
        header('Location: ?action=step1');
        exit;
    }
    sendResponse(200, renderPage('数据库配置', step2Page()));
}

function handleStep3(Installer $installer): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = [
            'host' => $_POST['db_host'] ?? '127.0.0.1',
            'port' => (int)($_POST['db_port'] ?? 3306),
            'database' => $_POST['db_database'] ?? 'game-platform',
            'username' => $_POST['db_username'] ?? 'root',
            'password' => $_POST['db_password'] ?? '',
        ];
        $result = $installer->testDbConnection($db['host'], $db['port'], $db['database'], $db['username'], $db['password']);
        if (!$result['success']) {
            sendResponse(200, renderPage('数据库配置', step2Page($result['message'], $db)));
            return;
        }
        sendResponse(200, renderPage('管理员配置', step3Page($db, $result)));
        return;
    }
    header('Location: ?action=step2');
    exit;
}

function handleStep4(Installer $installer): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=step2');
        exit;
    }

    $db = [
        'host' => $_POST['db_host'] ?? '127.0.0.1',
        'port' => (int)($_POST['db_port'] ?? 3306),
        'database' => $_POST['db_database'] ?? 'game-platform',
        'username' => $_POST['db_username'] ?? 'root',
        'password' => $_POST['db_password'] ?? '',
    ];

    $adminUser = $_POST['admin_username'] ?? '';
    $adminPass = $_POST['admin_password'] ?? '';
    $adminPassConfirm = $_POST['admin_password_confirm'] ?? '';

    $errors = [];
    if (strlen($adminUser) < 3 || strlen($adminUser) > 50) {
        $errors[] = '管理员用户名需要 3-50 个字符';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $adminUser)) {
        $errors[] = '管理员用户名只能包含字母、数字和下划线';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = '管理员密码至少需要 6 个字符';
    }
    if ($adminPass !== $adminPassConfirm) {
        $errors[] = '两次输入的密码不一致';
    }

    if (!empty($errors)) {
        $connResult = $installer->testDbConnection($db['host'], $db['port'], $db['database'], $db['username'], $db['password']);
        sendResponse(200, renderPage('管理员配置', step3Page($db, $connResult, implode('<br>', $errors), $adminUser)));
        return;
    }

    $configureService = !empty($_POST['configure_service']);
    $svcDb = [];
    if ($configureService && !empty($_POST['svc_db_host'])) {
        $svcDb = [
            'host' => $_POST['svc_db_host'] ?? $db['host'],
            'port' => (int)($_POST['svc_db_port'] ?? $db['port']),
            'database' => $_POST['svc_db_database'] ?? $db['database'],
            'username' => $_POST['svc_db_username'] ?? $db['username'],
            'password' => $_POST['svc_db_password'] ?? $db['password'],
        ];
    }

    $result = $installer->runInstall($db, $adminUser, $adminPass, $configureService, $svcDb);

    if ($result['success']) {
        sendResponse(200, renderPage('安装完成', step5Page($result)));
    } else {
        sendResponse(200, renderPage('安装失败', step4ErrorPage($result)));
    }
}

function handleTestDb(Installer $installer): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $host = $input['db_host'] ?? '127.0.0.1';
    $port = (int)($input['db_port'] ?? 3306);
    $database = $input['db_database'] ?? '';
    $username = $input['db_username'] ?? 'root';
    $password = $input['db_password'] ?? '';
    if (empty($database)) {
        sendJson(400, ['success' => false, 'message' => '请输入数据库名称']);
        return;
    }
    $result = $installer->testDbConnection($host, $port, $database, $username, $password);
    sendJson($result['success'] ? 200 : 400, $result);
}

function sendResponse(int $code, string $html): void
{
    http_response_code($code);
    echo $html;
    exit;
}

function sendJson(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function renderPage(string $title, string $content): string
{
    $titleHtml = htmlspecialchars($title);
    return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleHtml} — 全球游戏聚合平台 安装向导</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="installer">
        <header class="installer-header">
            <h1>全球游戏聚合平台</h1>
            <p class="subtitle">Global Game Platform — 一键安装向导</p>
        </header>
        <main class="installer-main">{$content}</main>
        <footer class="installer-footer">
            <p>Copyright (c) 2026 erik &lt;erik@erik.xyz&gt; — https://erik.xyz</p>
        </footer>
    </div>
</body>
</html>
HTML;
}

function step1Page(array $results, bool $allPassed): string
{
    $rows = '';
    foreach ($results as $r) {
        $icon = $r['ok'] ? '✓' : '✗';
        $cls = $r['ok'] ? 'ok' : 'fail';
        $name = htmlspecialchars($r['name']);
        $current = htmlspecialchars($r['current']);
        $required = htmlspecialchars($r['required']);
        $message = htmlspecialchars($r['message']);
        $rows .= "<tr class=\"{$cls}\"><td>{$icon} {$name}</td><td>{$current}</td><td>{$required}</td><td>{$message}</td></tr>";
    }

    $btnClass = $allPassed ? '' : 'disabled';
    $btnText = $allPassed ? '下一步：数据库配置 →' : '环境检查未通过，请解决以上问题后刷新页面';

    return <<<HTML
        <div class="step-indicator">
            <span class="step active">1. 环境检查</span>
            <span class="step">2. 数据库配置</span>
            <span class="step">3. 管理员配置</span>
            <span class="step">4. 安装完成</span>
        </div>
        <h2>环境检查</h2>
        <table class="check-table">
            <thead><tr><th>检查项</th><th>当前值</th><th>要求</th><th>状态</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <div class="btn-group">
            <a href="?action=step2" class="btn btn-primary {$btnClass}">{$btnText}</a>
        </div>
HTML;
}

function step2Page(string $error = '', array $prev = []): string
{
    $errorHtml = $error ? '<div class="alert alert-error">' . htmlspecialchars($error) . '</div>' : '';
    $v = fn(string $key, string $default = '') => htmlspecialchars($prev[$key] ?? $default);

    return <<<HTML
        <div class="step-indicator">
            <span class="step done">1. 环境检查 ✓</span>
            <span class="step active">2. 数据库配置</span>
            <span class="step">3. 管理员配置</span>
            <span class="step">4. 安装完成</span>
        </div>
        <h2>数据库配置</h2>
        {$errorHtml}
        <form method="post" action="?action=step3" id="db-form">
            <div class="form-group">
                <label for="db_host">数据库主机</label>
                <input type="text" id="db_host" name="db_host" value="{$v('db_host', '127.0.0.1')}" required>
            </div>
            <div class="form-group">
                <label for="db_port">端口</label>
                <input type="number" id="db_port" name="db_port" value="{$v('db_port', '3306')}" required>
            </div>
            <div class="form-group">
                <label for="db_database">数据库名称</label>
                <input type="text" id="db_database" name="db_database" value="{$v('db_database', 'game-platform')}" required>
                <span class="form-hint">如果数据库不存在，安装向导将自动创建</span>
            </div>
            <div class="form-group">
                <label for="db_username">数据库用户名</label>
                <input type="text" id="db_username" name="db_username" value="{$v('db_username', 'root')}" required>
            </div>
            <div class="form-group">
                <label for="db_password">数据库密码</label>
                <input type="password" id="db_password" name="db_password" value="{$v('db_password')}">
            </div>
            <div class="btn-group">
                <button type="button" id="test-db-btn" class="btn btn-secondary">测试连接</button>
                <button type="submit" class="btn btn-primary">下一步：管理员配置 →</button>
            </div>
            <div id="test-result" class="test-result"></div>
        </form>
        <script>
        document.getElementById('test-db-btn').addEventListener('click', async function() {
            const btn = this, r = document.getElementById('test-result');
            btn.disabled = true; btn.textContent = '测试中...'; r.innerHTML = '';
            const data = {
                db_host: document.getElementById('db_host').value,
                db_port: document.getElementById('db_port').value,
                db_database: document.getElementById('db_database').value,
                db_username: document.getElementById('db_username').value,
                db_password: document.getElementById('db_password').value,
            };
            try {
                const resp = await fetch('?action=test-db', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
                const json = await resp.json();
                if (json.success) {
                    r.innerHTML = '<div class="alert alert-success">✓ 连接成功 — MySQL ' + json.version + (json.db_created ? '（数据库已自动创建）' : '') + (json.existing_tables > 0 ? ' ⚠ 检测到已有 ' + json.existing_tables + ' 张 game_ 表，继续安装将覆盖已有数据' : '') + '</div>';
                } else {
                    r.innerHTML = '<div class="alert alert-error">✗ ' + json.message + '</div>';
                }
            } catch(e) {
                r.innerHTML = '<div class="alert alert-error">请求失败: ' + e.message + '</div>';
            } finally {
                btn.disabled = false; btn.textContent = '测试连接';
            }
        });
        </script>
HTML;
}

function step3Page(array $db, array $dbResult, string $error = '', string $prevAdminUser = ''): string
{
    $errorHtml = $error ? '<div class="alert alert-error">' . $error . '</div>' : '';
    $v = fn(string $key) => htmlspecialchars($db[$key] ?? '');

    $existingWarning = '';
    if ($dbResult['existing_tables'] > 0) {
        $existingWarning = '<div class="alert alert-warning">⚠ 检测到数据库中已有 ' . $dbResult['existing_tables'] . ' 张 game_ 表，继续安装将覆盖已有数据！</div>';
    }

    return <<<HTML
        <div class="step-indicator">
            <span class="step done">1. 环境检查 ✓</span>
            <span class="step done">2. 数据库配置 ✓</span>
            <span class="step active">3. 管理员配置</span>
            <span class="step">4. 安装完成</span>
        </div>
        <h2>管理员配置</h2>
        <p class="db-info">数据库: {$v('username')}@{$v('host')}:{$v('port')}/{$v('database')} (MySQL {$dbResult['version']})</p>
        {$existingWarning}
        {$errorHtml}
        <form method="post" action="?action=step4" id="install-form">
            <input type="hidden" name="db_host" value="{$v('host')}">
            <input type="hidden" name="db_port" value="{$v('port')}">
            <input type="hidden" name="db_database" value="{$v('database')}">
            <input type="hidden" name="db_username" value="{$v('username')}">
            <input type="hidden" name="db_password" value="{$v('password')}">

            <fieldset>
                <legend>后台管理员账户</legend>
                <div class="form-group">
                    <label for="admin_username">管理员用户名</label>
                    <input type="text" id="admin_username" name="admin_username" value="{$prevAdminUser}" placeholder="admin" required>
                    <span class="form-hint">3-50个字符，只能包含字母、数字和下划线</span>
                </div>
                <div class="form-group">
                    <label for="admin_password">管理员密码</label>
                    <input type="password" id="admin_password" name="admin_password" placeholder="至少6个字符" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">确认密码</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" placeholder="再次输入密码" required minlength="6">
                </div>
            </fieldset>

            <fieldset>
                <legend>C端业务端 (Service) 配置 <span class="tag-optional">可选</span></legend>
                <div class="form-group">
                    <label><input type="checkbox" id="configure_service" name="configure_service" value="1" checked> 同时配置 service 应用的 .env 文件</label>
                </div>
                <div id="svc-db-fields" class="svc-fields">
                    <p class="form-hint">默认与 Admin 使用相同的数据库配置。如需使用不同的数据库实例，请修改以下配置：</p>
                    <div class="form-group">
                        <label for="svc_db_host">Service 数据库主机</label>
                        <input type="text" id="svc_db_host" name="svc_db_host" value="{$v('host')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_port">Service 端口</label>
                        <input type="number" id="svc_db_port" name="svc_db_port" value="{$v('port')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_database">Service 数据库名称</label>
                        <input type="text" id="svc_db_database" name="svc_db_database" value="{$v('database')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_username">Service 数据库用户名</label>
                        <input type="text" id="svc_db_username" name="svc_db_username" value="{$v('username')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_password">Service 数据库密码</label>
                        <input type="password" id="svc_db_password" name="svc_db_password" value="{$v('password')}">
                    </div>
                </div>
            </fieldset>

            <div class="btn-group">
                <a href="?action=step2" class="btn btn-secondary">← 返回</a>
                <button type="submit" class="btn btn-primary" id="install-btn">开始安装</button>
            </div>
        </form>
        <script>
        document.getElementById('configure_service').addEventListener('change', function() {
            document.getElementById('svc-db-fields').style.display = this.checked ? 'block' : 'none';
        });
        document.getElementById('install-form').addEventListener('submit', function() {
            var btn = document.getElementById('install-btn');
            btn.disabled = true; btn.textContent = '安装中，请稍候...'; btn.classList.add('disabled');
        });
        </script>
HTML;
}

function step5Page(array $result): string
{
    $stepsHtml = '';
    foreach ($result['steps'] as $step) {
        $icon = $step['ok'] ? '✓' : '✗';
        $name = htmlspecialchars($step['name']);
        $message = htmlspecialchars($step['message']);
        $cls = $step['ok'] ? 'ok' : 'fail';
        $stepsHtml .= "<li class=\"{$cls}\">{$icon} <strong>{$name}</strong>: {$message}</li>";
    }

    return <<<HTML
        <div class="step-indicator">
            <span class="step done">1. 环境检查 ✓</span>
            <span class="step done">2. 数据库配置 ✓</span>
            <span class="step done">3. 管理员配置 ✓</span>
            <span class="step active">4. 安装完成</span>
        </div>
        <div class="install-success">
            <div class="success-icon">✓</div>
            <h2>安装完成！</h2>
            <p>全球游戏聚合平台已成功安装。</p>
        </div>
        <div class="install-summary">
            <h3>安装步骤</h3>
            <ol class="install-steps">{$stepsHtml}</ol>
        </div>
        <div class="next-steps">
            <h3>下一步</h3>
            <ol>
                <li>进入 <code>admin/</code> 目录，运行 <code>composer install</code> 安装 PHP 依赖</li>
                <li>进入 <code>service/</code> 目录，运行 <code>composer install</code> 安装 PHP 依赖</li>
                <li>启动管理后台: <code>cd admin && php start.php start -d</code>（端口 8787）</li>
                <li>启动C端业务: <code>cd service && php start.php start -d</code>（端口 8788）</li>
                <li>访问管理后台: <a href="http://localhost:8787" target="_blank">http://localhost:8787</a></li>
                <li>使用设置的用户名和密码登录管理后台</li>
            </ol>
        </div>
        <div class="security-notice">
            <h3>⚠ 安全提醒</h3>
            <ul>
                <li>安装完成后，请立即删除 <code>install/</code> 目录，防止被恶意利用</li>
                <li>生产环境请将 admin/.env 中的 <code>APP_DEBUG</code> 设为 <code>false</code></li>
                <li>生产环境请修改 JWT_SECRET 和 ENCRYPTION_KEY 为你自己的随机字符串</li>
                <li>请确保 Redis 服务已启动（127.0.0.1:6379）</li>
            </ul>
        </div>
HTML;
}

function step4ErrorPage(array $result): string
{
    $stepsHtml = '';
    foreach ($result['steps'] as $step) {
        $icon = $step['ok'] ? '✓' : '✗';
        $name = htmlspecialchars($step['name']);
        $message = htmlspecialchars($step['message']);
        $cls = $step['ok'] ? 'ok' : 'fail';
        $stepsHtml .= "<li class=\"{$cls}\">{$icon} <strong>{$name}</strong>: {$message}</li>";
    }
    $msg = htmlspecialchars($result['message']);

    return <<<HTML
        <div class="step-indicator">
            <span class="step done">1. 环境检查 ✓</span>
            <span class="step done">2. 数据库配置 ✓</span>
            <span class="step done">3. 管理员配置 ✓</span>
            <span class="step active">4. 安装失败</span>
        </div>
        <div class="install-fail">
            <div class="fail-icon">✗</div>
            <h2>安装失败</h2>
            <p class="error-message">{$msg}</p>
        </div>
        <div class="install-summary">
            <h3>已执行的步骤</h3>
            <ol class="install-steps">{$stepsHtml}</ol>
        </div>
        <div class="btn-group">
            <a href="?action=step2" class="btn btn-primary">重新安装</a>
        </div>
HTML;
}

function installedPage(array $lockData): string
{
    $time = htmlspecialchars($lockData['installed_at'] ?? '未知');
    $admin = htmlspecialchars($lockData['admin_username'] ?? '未知');
    return <<<HTML
        <div class="already-installed">
            <div class="success-icon">✓</div>
            <h2>系统已安装</h2>
            <p>安装时间: {$time}</p>
            <p>管理员: {$admin}</p>
            <p class="notice">如需重新安装，请删除 <code>install/install.lock</code> 文件后刷新此页面。</p>
        </div>
HTML;
}
