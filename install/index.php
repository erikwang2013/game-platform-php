<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 一键安装向导 — 入口文件
 *
 * 使用方法: php -S 0.0.0.0:8888 -t install/
 */

require __DIR__ . '/lang.php';
require __DIR__ . '/Installer.php';

$installer = new Installer();

if ($installer->isInstalled()) {
    // 安装已结束：语言偏好 cookie 随之过期，避免残留状态
    setcookie('installer_lang', '', ['expires' => time() - 3600, 'path' => '/']);
    $lockData = json_decode(file_get_contents($installer->getLockFile()), true);
    sendResponse(200, renderPage('installed_title', installedPage($lockData)));
}

$action = $_GET['action'] ?? 'step1';

// 先落语言 cookie：重定向类请求(如失败页 ?action=step4&lang=xx)不在本页渲染，若不在此处理语言选择会丢失
installer_current_lang();

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
    sendResponse(200, renderPage('step1_heading', step1Page($results, $allPassed)));
}

function handleStep2(Installer $installer): void
{
    $results = $installer->checkEnvironment();
    if (!$installer->allEnvChecksPassed()) {
        header('Location: ?action=step1');
        exit;
    }
    sendResponse(200, renderPage('step2_heading', step2Page()));
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
            sendResponse(200, renderPage('step2_heading', step2Page(dbErrorMessage($result), $db)));
            return;
        }
        sendResponse(200, renderPage('step3_heading', step3Page($db, $result, '', '', !empty($_POST['install_test_data']))));
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

    // 流式进度：前端带 X-Installer-Progress: 1 提交时以 NDJSON 逐行推送安装步骤，末行携带结果页 HTML；
    // 无该请求头（JS 不可用/旧浏览器）仍走整页响应，行为不变
    $progressMode = ($_SERVER['HTTP_X_INSTALLER_PROGRESS'] ?? '') === '1';
    if ($progressMode) {
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('X-Accel-Buffering: no'); // 反代默认缓冲会憋住整段响应，显式要求逐块透传
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
    }
    $reply = static function (string $page) use ($progressMode): void {
        if ($progressMode) {
            echo json_encode(['done' => true, 'html' => $page], JSON_UNESCAPED_UNICODE) . "\n";
            flush();
            exit;
        }
        sendResponse(200, $page);
    };
    $progress = $progressMode
        ? static function (string $key, int $completed, int $total): void {
            echo json_encode(['key' => $key, 'i' => $completed, 'total' => $total, 'label' => t($key)], JSON_UNESCAPED_UNICODE) . "\n";
            flush();
        }
        : null;

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
        $errors[] = t('err_username_len');
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $adminUser)) {
        $errors[] = t('err_username_chars');
    }
    if (strlen($adminPass) < 6) {
        $errors[] = t('err_password_len');
    }
    if ($adminPass !== $adminPassConfirm) {
        $errors[] = t('err_password_mismatch');
    }

    if (!empty($errors)) {
        $connResult = $installer->testDbConnection($db['host'], $db['port'], $db['database'], $db['username'], $db['password'])
            + ['version' => '?', 'existing_tables' => 0];
        $reply(renderPage('step3_heading', step3Page($db, $connResult, implode('<br>', array_map('htmlspecialchars', $errors)), $adminUser, !empty($_POST['install_test_data']))));
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
    $installTestData = !empty($_POST['install_test_data']);

    $result = $installer->runInstall($db, $adminUser, $adminPass, $configureService, $svcDb, $installTestData, $progress);

    if ($result['success']) {
        // 安装完成：页面脚本按 data-clear-saved 清理浏览器保存的填写内容；
        // 语言 cookie 不在此清（否则成功页会丢失所选语言），留待下次进入已安装页时过期
        $reply(renderPage('success_title', step5Page($result), true));
    } else {
        $reply(renderPage('fail_title', step4ErrorPage($result)));
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
        sendJson(400, ['success' => false, 'message' => t('db_name_required')]);
        return;
    }
    $result = $installer->testDbConnection($host, $port, $database, $username, $password);
    if (empty($result['success'])) {
        $result['message'] = dbErrorMessage($result);
    }
    sendJson($result['success'] ? 200 : 400, $result);
}

function dbErrorMessage(array $result): string
{
    return t($result['message_key'] ?? 'db_err_generic', $result['message_params'] ?? []);
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

function renderPage(string $titleKey, string $content, bool $clearSaved = false): string
{
    $lang = installer_current_lang();
    $dir = $lang === 'ar' ? 'rtl' : 'ltr';
    $titleHtml = htmlspecialchars(t($titleKey));
    $siteName = htmlspecialchars(t('site_name'));
    $subtitle = htmlspecialchars(t('site_subtitle'));
    $langLabel = htmlspecialchars(t('lang_select_label'));
    $langOptions = '';
    foreach (installer_languages() as $code => $nativeName) {
        $selected = $code === $lang ? ' selected' : '';
        $langOptions .= '<option value="' . htmlspecialchars($code) . '"' . $selected . '>' . htmlspecialchars($nativeName) . '</option>';
    }
    $currentAction = htmlspecialchars($_GET['action'] ?? 'step1');
    $bodyAttr = $clearSaved ? ' data-clear-saved="1"' : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}" dir="{$dir}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleHtml} — {$siteName}</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body{$bodyAttr}>
    <div class="installer">
        <header class="installer-header">
            <h1>{$siteName}</h1>
            <p class="subtitle">{$subtitle}</p>
            <div class="lang-switch">
                <label for="lang-select">{$langLabel}</label>
                <select id="lang-select" data-action="{$currentAction}">{$langOptions}</select>
            </div>
        </header>
        <main class="installer-main">{$content}</main>
        <footer class="installer-footer">
            <p>Copyright (c) 2026 erik &lt;erik@erik.xyz&gt; — https://erik.xyz</p>
        </footer>
    </div>
    <script>
    (function () {
        var sel = document.getElementById('lang-select');
        if (sel) {
            sel.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('lang', sel.value);
                if (sel.dataset.action) {
                    url.searchParams.set('action', sel.dataset.action);
                }
                window.location.href = url.toString();
            });
        }

        // 表单填写记忆: 浏览器本地保存，返回上一步/刷新后自动恢复
        var PREFIX = 'gp_installer:';
        document.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
            if (el.type === 'hidden') {
                return; // 隐藏域由服务端以校验过的连接配置填充，不参与记忆以免旧值覆盖
            }
            var key = PREFIX + el.name;
            var saved = window.localStorage.getItem(key);
            var isToggle = el.type === 'checkbox' || el.type === 'radio';
            var eventName = (isToggle || el.tagName === 'SELECT') ? 'change' : 'input';
            if (saved !== null) {
                if (isToggle) {
                    el.checked = saved === '1';
                } else {
                    el.value = saved;
                }
            }
            el.addEventListener(eventName, function () {
                window.localStorage.setItem(key, isToggle ? (el.checked ? '1' : '0') : el.value);
            });
            // 恢复后补发 change 事件，让页面自身的联动逻辑（如 service 字段显隐）跟上恢复状态
            if (saved !== null && (isToggle || el.tagName === 'SELECT')) {
                el.dispatchEvent(new Event('change'));
            }
        });

        // 安装完成: 清空浏览器中保存的全部填写内容
        if (document.body.dataset.clearSaved) {
            Object.keys(window.localStorage).forEach(function (k) {
                if (k.indexOf(PREFIX) === 0) {
                    window.localStorage.removeItem(k);
                }
            });
        }
    })();
    </script>
</body>
</html>
HTML;
}

function stepIndicator(int $current, bool $failed = false): string
{
    $labels = [
        1 => t('step1'),
        2 => t('step2'),
        3 => t('step3'),
        $failed ? 5 : 4 => $failed ? t('step4_failed') : t('step4'),
    ];
    $html = '<div class="step-indicator">';
    for ($i = 1; $i <= 4; $i++) {
        $label = $labels[$i] ?? '';
        if ($i < $current) {
            $html .= '<span class="step done">' . htmlspecialchars($label) . ' ✓</span>';
        } elseif ($i === $current) {
            $html .= '<span class="step active">' . htmlspecialchars($label) . '</span>';
        } else {
            $html .= '<span class="step">' . htmlspecialchars($label) . '</span>';
        }
    }
    return $html . '</div>';
}

function step1Page(array $results, bool $allPassed): string
{
    $rows = '';
    foreach ($results as $r) {
        $icon = $r['ok'] ? '✓' : '✗';
        $cls = $r['ok'] ? 'ok' : 'fail';
        $name = htmlspecialchars(t($r['name_key']));
        $current = htmlspecialchars(isset($r['current']['key'])
            ? t($r['current']['key'], $r['current']['params'] ?? [])
            : (string)($r['current']['text'] ?? ''));
        $required = htmlspecialchars(t($r['blocking'] ? 'must' : 'suggested'));
        $message = htmlspecialchars($r['ok'] ? t('pass') : t($r['fail_key']));
        $rows .= "<tr class=\"{$cls}\"><td>{$icon} {$name}</td><td>{$current}</td><td>{$required}</td><td>{$message}</td></tr>";
    }

    $btnClass = $allPassed ? '' : 'disabled';
    $btnText = htmlspecialchars($allPassed ? t('btn_next_step2') : t('env_check_failed'));
    $indicator = stepIndicator(1);
    $heading = htmlspecialchars(t('step1_heading'));
    $thItem = htmlspecialchars(t('th_item'));
    $thCurrent = htmlspecialchars(t('th_current'));
    $thRequired = htmlspecialchars(t('th_required'));
    $thStatus = htmlspecialchars(t('th_status'));

    return <<<HTML
        {$indicator}
        <h2>{$heading}</h2>
        <table class="check-table">
            <thead><tr><th>{$thItem}</th><th>{$thCurrent}</th><th>{$thRequired}</th><th>{$thStatus}</th></tr></thead>
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
    $indicator = stepIndicator(2);
    $h2 = htmlspecialchars(t('step2_heading'));
    $lHost = htmlspecialchars(t('db_host'));
    $lPort = htmlspecialchars(t('db_port'));
    $lDatabase = htmlspecialchars(t('db_database'));
    $lDatabaseHint = htmlspecialchars(t('db_database_hint'));
    $lUsername = htmlspecialchars(t('db_username'));
    $lPassword = htmlspecialchars(t('db_password'));
    $lTestDb = htmlspecialchars(t('btn_test_db'));
    $lNext = htmlspecialchars(t('btn_next_step3'));
    $lBack = htmlspecialchars(t('btn_back'));
    $lOptionsLegend = htmlspecialchars(t('legend_options'));
    $lInstallTestData = htmlspecialchars(t('install_test_data'));
    $lInstallTestDataHint = htmlspecialchars(t('install_test_data_hint'));
    $jsStrings = json_encode([
        'ok' => t('ajax_db_test_ok'),
        'created' => t('ajax_db_created'),
        'existing' => t('ajax_existing_tables'),
        'failed' => t('ajax_request_failed'),
        'testing' => t('btn_testing'),
        'test' => t('btn_test_db'),
    ], JSON_UNESCAPED_UNICODE);

    return <<<HTML
        {$indicator}
        <h2>{$h2}</h2>
        {$errorHtml}
        <form method="post" action="?action=step3" id="db-form">
            <div class="form-group">
                <label for="db_host">{$lHost}</label>
                <input type="text" id="db_host" name="db_host" value="{$v('db_host', '127.0.0.1')}" required>
            </div>
            <div class="form-group">
                <label for="db_port">{$lPort}</label>
                <input type="number" id="db_port" name="db_port" value="{$v('db_port', '3306')}" required>
            </div>
            <div class="form-group">
                <label for="db_database">{$lDatabase}</label>
                <input type="text" id="db_database" name="db_database" value="{$v('db_database', 'game-platform')}" required>
                <span class="form-hint">{$lDatabaseHint}</span>
            </div>
            <div class="form-group">
                <label for="db_username">{$lUsername}</label>
                <input type="text" id="db_username" name="db_username" value="{$v('db_username', 'root')}" required>
            </div>
            <div class="form-group">
                <label for="db_password">{$lPassword}</label>
                <input type="password" id="db_password" name="db_password" value="{$v('db_password')}">
            </div>
            <fieldset>
                <legend>{$lOptionsLegend}</legend>
                <div class="form-group">
                    <label><input type="checkbox" id="install_test_data" name="install_test_data" value="1"> {$lInstallTestData}</label>
                    <span class="form-hint">{$lInstallTestDataHint}</span>
                </div>
            </fieldset>
            <div class="btn-group">
                <a href="?action=step1" class="btn btn-secondary">{$lBack}</a>
                <button type="button" id="test-db-btn" class="btn btn-secondary">{$lTestDb}</button>
                <button type="submit" class="btn btn-primary">{$lNext}</button>
            </div>
            <div id="test-result" class="test-result"></div>
        </form>
        <script>
        var I18N = {$jsStrings};
        document.getElementById('test-db-btn').addEventListener('click', async function() {
            const btn = this, r = document.getElementById('test-result');
            btn.disabled = true; btn.textContent = I18N.testing; r.innerHTML = '';
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
                    var msg = I18N.ok.replace(':version', json.version);
                    if (json.db_created) { msg += I18N.created; }
                    if (json.existing_tables > 0) { msg += I18N.existing.replace(':count', json.existing_tables); }
                    r.innerHTML = '<div class="alert alert-success">' + msg + '</div>';
                } else {
                    r.innerHTML = '<div class="alert alert-error">✗ ' + json.message + '</div>';
                }
            } catch(e) {
                r.innerHTML = '<div class="alert alert-error">' + I18N.failed.replace(':error', e.message) + '</div>';
            } finally {
                btn.disabled = false; btn.textContent = I18N.test;
            }
        });
        </script>
HTML;
}

function step3Page(array $db, array $dbResult, string $error = '', string $prevAdminUser = '', bool $installTestData = false): string
{
    $errorHtml = $error ? '<div class="alert alert-error">' . $error . '</div>' : '';
    $v = fn(string $key) => htmlspecialchars($db[$key] ?? '');

    $existingWarning = '';
    if (($dbResult['existing_tables'] ?? 0) > 0) {
        $existingWarning = '<div class="alert alert-warning">' . htmlspecialchars(t('existing_tables_warning', ['count' => $dbResult['existing_tables']])) . '</div>';
    }
    $indicator = stepIndicator(3);
    $dbInfo = htmlspecialchars(t('db_info_label')) . ' ' . $v('username') . '@' . $v('host') . ':' . $v('port') . ' / ' . $v('database') . ' (MySQL ' . htmlspecialchars((string)($dbResult['version'] ?? '?')) . ')';
    $prevAdminUser = htmlspecialchars($prevAdminUser);
    $h2 = htmlspecialchars(t('step3_heading'));
    $lAdminAccount = htmlspecialchars(t('legend_admin'));
    $lAdminUser = htmlspecialchars(t('admin_username'));
    $lAdminUserHint = htmlspecialchars(t('admin_username_hint'));
    $lAdminPass = htmlspecialchars(t('admin_password'));
    $lAdminPassHint = htmlspecialchars(t('admin_password_hint'));
    $lAdminPassConfirm = htmlspecialchars(t('admin_password_confirm'));
    $lAdminPassConfirmHint = htmlspecialchars(t('admin_password_confirm_hint'));
    $lServiceLegend = htmlspecialchars(t('legend_service'));
    $lOptional = htmlspecialchars(t('tag_optional'));
    $lConfigureService = htmlspecialchars(t('configure_service'));
    $lSvcHint = htmlspecialchars(t('svc_db_hint'));
    $lSvcHost = htmlspecialchars(t('svc_db_host'));
    $lSvcPort = htmlspecialchars(t('svc_db_port'));
    $lSvcDatabase = htmlspecialchars(t('svc_db_database'));
    $lSvcUsername = htmlspecialchars(t('svc_db_username'));
    $lSvcPassword = htmlspecialchars(t('svc_db_password'));
    $lBack = htmlspecialchars(t('btn_back'));
    $testDataValue = $installTestData ? '1' : '0';
    $lInstall = htmlspecialchars(t('btn_install'));
    $lInstalling = htmlspecialchars(t('btn_installing'));
    $jsInstalling = json_encode(t('btn_installing'), JSON_UNESCAPED_UNICODE);
    $jsInstallBtn = json_encode(t('btn_install'), JSON_UNESCAPED_UNICODE);
    $jsRequestFailed = json_encode(t('ajax_request_failed'), JSON_UNESCAPED_UNICODE);

    return <<<HTML
        {$indicator}
        <h2>{$h2}</h2>
        <p class="db-info">{$dbInfo}</p>
        {$existingWarning}
        {$errorHtml}
        <div id="install-error"></div>
        <form method="post" action="?action=step4" id="install-form">
            <input type="hidden" name="db_host" value="{$v('host')}">
            <input type="hidden" name="db_port" value="{$v('port')}">
            <input type="hidden" name="db_database" value="{$v('database')}">
            <input type="hidden" name="db_username" value="{$v('username')}">
            <input type="hidden" name="db_password" value="{$v('password')}">
            <input type="hidden" name="install_test_data" value="{$testDataValue}">

            <fieldset>
                <legend>{$lAdminAccount}</legend>
                <div class="form-group">
                    <label for="admin_username">{$lAdminUser}</label>
                    <input type="text" id="admin_username" name="admin_username" value="{$prevAdminUser}" placeholder="admin" required>
                    <span class="form-hint">{$lAdminUserHint}</span>
                </div>
                <div class="form-group">
                    <label for="admin_password">{$lAdminPass}</label>
                    <input type="password" id="admin_password" name="admin_password" placeholder="{$lAdminPassHint}" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">{$lAdminPassConfirm}</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" placeholder="{$lAdminPassConfirmHint}" required minlength="6">
                </div>
            </fieldset>

            <fieldset>
                <legend>{$lServiceLegend} <span class="tag-optional">{$lOptional}</span></legend>
                <div class="form-group">
                    <label><input type="checkbox" id="configure_service" name="configure_service" value="1" checked> {$lConfigureService}</label>
                </div>
                <div id="svc-db-fields" class="svc-fields">
                    <p class="form-hint">{$lSvcHint}</p>
                    <div class="form-group">
                        <label for="svc_db_host">{$lSvcHost}</label>
                        <input type="text" id="svc_db_host" name="svc_db_host" value="{$v('host')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_port">{$lSvcPort}</label>
                        <input type="number" id="svc_db_port" name="svc_db_port" value="{$v('port')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_database">{$lSvcDatabase}</label>
                        <input type="text" id="svc_db_database" name="svc_db_database" value="{$v('database')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_username">{$lSvcUsername}</label>
                        <input type="text" id="svc_db_username" name="svc_db_username" value="{$v('username')}">
                    </div>
                    <div class="form-group">
                        <label for="svc_db_password">{$lSvcPassword}</label>
                        <input type="password" id="svc_db_password" name="svc_db_password" value="{$v('password')}">
                    </div>
                </div>
            </fieldset>

            <div class="btn-group">
                <a href="?action=step2" class="btn btn-secondary">{$lBack}</a>
                <button type="submit" class="btn btn-primary" id="install-btn">{$lInstall}</button>
            </div>
        </form>
        <div id="install-modal" class="modal-mask">
            <div class="modal">
                <h3>{$lInstalling}</h3>
                <div class="progress"><div id="install-bar" class="progress-bar"></div></div>
                <p id="install-stage" class="progress-stage"></p>
            </div>
        </div>
        <script>
        var INSTALLING_TEXT = {$jsInstalling};
        var INSTALL_BTN_TEXT = {$jsInstallBtn};
        var REQUEST_FAILED_TEXT = {$jsRequestFailed};
        document.getElementById('configure_service').addEventListener('change', function() {
            document.getElementById('svc-db-fields').style.display = this.checked ? 'block' : 'none';
        });

        var installForm = document.getElementById('install-form');
        var installModal = document.getElementById('install-modal');
        var installBar = document.getElementById('install-bar');
        var installStage = document.getElementById('install-stage');
        var finished = false;

        function installFailed(message) {
            // 不自动重发（服务端可能已开始安装），留在第 3 步由用户决定是否重试
            installModal.classList.remove('show');
            var btn = document.getElementById('install-btn');
            btn.disabled = false; btn.textContent = INSTALL_BTN_TEXT; btn.classList.remove('disabled');
            document.getElementById('install-error').innerHTML = '<div class="alert alert-error">' + REQUEST_FAILED_TEXT.replace(':error', message) + '</div>';
        }

        installForm.addEventListener('submit', function(e) {
            var btn = document.getElementById('install-btn');
            btn.disabled = true; btn.textContent = INSTALLING_TEXT; btn.classList.add('disabled');
            // 不支持流式读取的浏览器：不拦截，交给原生表单提交（服务端返回整页）
            if (!window.fetch || !window.FormData || !window.TextDecoder) { return; }
            e.preventDefault();
            installModal.classList.add('show');
            installStage.textContent = '';
            installBar.style.width = '4%';
            document.getElementById('install-error').innerHTML = '';

            fetch(installForm.action, { method: 'POST', body: new FormData(installForm), headers: { 'X-Installer-Progress': '1' } })
                .then(function(resp) {
                    if (!resp.ok || !resp.body) { throw new Error('HTTP ' + resp.status); }
                    var reader = resp.body.getReader();
                    var decoder = new TextDecoder();
                    var buf = '';
                    var pump = function() {
                        return reader.read().then(function(chunk) {
                            if (chunk.done || finished) { return; }
                            buf += decoder.decode(chunk.value, { stream: true });
                            var lines = buf.split('\\n');
                            buf = lines.pop();
                            lines.forEach(function(line) {
                                if (!line || finished) { return; }
                                var msg = JSON.parse(line);
                                if (msg.done) {
                                    // 结果页整页替换当前文档；其中的内联脚本（清空表单记忆等）照常执行
                                    finished = true;
                                    document.open();
                                    document.write(msg.html);
                                    document.close();
                                    return;
                                }
                                installBar.style.width = Math.round(msg.i / msg.total * 100) + '%';
                                installStage.textContent = msg.i + '/' + msg.total + ' ' + msg.label;
                            });
                            return pump();
                        });
                    };
                    return pump();
                })
                .catch(function(err) {
                    if (!finished) { installFailed(err.message); }
                });
        });
        </script>
HTML;
}

/**
 * 读取生成的 .env 中的配置值（安装成功页展示用）；文件不存在或键缺失时返回空串
 */
function envConfigValue(string $file, string $key): string
{
    if (!is_file($file)) {
        return '';
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/', $line, $m)) {
            return trim($m[1], " \t\"'");
        }
    }
    return '';
}

function step5Page(array $result): string
{
    $stepsHtml = renderSteps($result['steps']);
    $indicator = stepIndicator(5);
    // 后台地址跟随生成的 admin/.env 的 APP_URL（用户在模板里改域名/端口，向导链接同步）
    $nextUrl = htmlspecialchars(envConfigValue(dirname(__DIR__) . '/admin/.env', 'APP_URL') ?: 'http://localhost:8789');
    $successTitle = htmlspecialchars(t('success_title'));
    $successDesc = htmlspecialchars(t('success_desc'));
    $summaryTitle = htmlspecialchars(t('summary_title'));
    $nextTitle = htmlspecialchars(t('next_steps_title'));
    $next1 = htmlspecialchars(t('next_step_1'));
    $next2 = htmlspecialchars(t('next_step_2'));
    $next3 = htmlspecialchars(t('next_step_3'));
    $next4 = htmlspecialchars(t('next_step_4'));
    $next5 = htmlspecialchars(t('next_step_5'));
    $next6 = htmlspecialchars(t('next_step_6'));
    $secTitle = htmlspecialchars(t('security_title'));
    $sec1 = htmlspecialchars(t('security_1'));
    $sec2 = htmlspecialchars(t('security_2'));
    $sec3 = htmlspecialchars(t('security_3'));
    $sec4 = htmlspecialchars(t('security_4'));

    return <<<HTML
        {$indicator}
        <div class="install-success">
            <div class="success-icon">✓</div>
            <h2>{$successTitle}</h2>
            <p>{$successDesc}</p>
        </div>
        <div class="install-summary">
            <h3>{$summaryTitle}</h3>
            <ol class="install-steps">{$stepsHtml}</ol>
        </div>
        <div class="next-steps">
            <h3>{$nextTitle}</h3>
            <ol>
                <li>{$next1}</li>
                <li>{$next2}</li>
                <li>{$next3}</li>
                <li>{$next4}</li>
                <li>{$next5} <a href="{$nextUrl}" target="_blank">{$nextUrl}</a></li>
                <li>{$next6}</li>
            </ol>
        </div>
        <div class="security-notice">
            <h3>{$secTitle}</h3>
            <ul>
                <li>{$sec1}</li>
                <li>{$sec2}</li>
                <li>{$sec3}</li>
                <li>{$sec4}</li>
            </ul>
        </div>
HTML;
}

function step4ErrorPage(array $result): string
{
    $stepsHtml = renderSteps($result['steps'] ?? []);
    $msg = htmlspecialchars(t($result['message_key'] ?? 'install_fail_generic', $result['params'] ?? []));
    $indicator = stepIndicator(4, true);
    $failTitle = htmlspecialchars(t('fail_title'));
    $failedSummaryTitle = htmlspecialchars(t('summary_title_failed'));
    $reinstallText = htmlspecialchars(t('btn_reinstall'));

    return <<<HTML
        {$indicator}
        <div class="install-fail">
            <div class="fail-icon">✗</div>
            <h2>{$failTitle}</h2>
            <p class="error-message">{$msg}</p>
        </div>
        <div class="install-summary">
            <h3>{$failedSummaryTitle}</h3>
            <ol class="install-steps">{$stepsHtml}</ol>
        </div>
        <div class="btn-group">
            <a href="?action=step2" class="btn btn-primary">{$reinstallText}</a>
        </div>
HTML;
}

function renderSteps(array $steps): string
{
    $html = '';
    foreach ($steps as $step) {
        $icon = $step['ok'] ? '✓' : '✗';
        $name = htmlspecialchars(t($step['name_key'] ?? 'unknown'));
        $message = htmlspecialchars(t($step['message_key'] ?? '', $step['params'] ?? []));
        $cls = $step['ok'] ? 'ok' : 'fail';
        $html .= "<li class=\"{$cls}\">{$icon} <strong>{$name}</strong>: {$message}</li>";
    }
    return $html;
}

function installedPage(array $lockData): string
{
    $time = htmlspecialchars((string)($lockData['installed_at'] ?? t('installed_unknown')));
    $admin = htmlspecialchars((string)($lockData['admin_username'] ?? t('installed_unknown')));
    $installedTime = htmlspecialchars(t('installed_time'));
    $installedAdmin = htmlspecialchars(t('installed_admin'));
    $installedNotice = htmlspecialchars(t('installed_notice'));
    $installedTitle = htmlspecialchars(t('installed_title'));

    return <<<HTML
        <div class="already-installed">
            <div class="success-icon">✓</div>
            <h2>{$installedTitle}</h2>
            <p>{$installedTime}: {$time}</p>
            <p>{$installedAdmin}: {$admin}</p>
            <p class="notice">{$installedNotice}</p>
        </div>
HTML;
}
