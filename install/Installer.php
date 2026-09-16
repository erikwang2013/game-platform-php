<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 一键安装向导 — 核心逻辑
 */

class Installer
{
    private string $installDir;
    private string $lockFile;
    private string $sqlFile;
    private int $currentStep = 1;
    private array $errors = [];
    private array $envCheckResults = [];

    public function __construct()
    {
        $this->installDir = __DIR__;
        $this->lockFile = $this->installDir . '/install.lock';
        $this->sqlFile = $this->installDir . '/install.sql';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->lockFile);
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function setCurrentStep(int $step): void
    {
        $this->currentStep = max(1, min(5, $step));
    }

    public function getLockFile(): string
    {
        return $this->lockFile;
    }

    // ── Step 1: 环境检查 ──

    public function checkEnvironment(): array
    {
        $results = [];

        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.1.0', '>=');
        $results[] = [
            'name_key' => 'check_php_version',
            'current' => ['text' => $phpVersion],
            'ok' => $phpOk,
            'blocking' => true,
            'fail_key' => 'check_php_version_fail',
        ];

        $pdoOk = extension_loaded('pdo_mysql');
        $results[] = [
            'name_key' => 'check_pdo',
            'current' => ['key' => $pdoOk ? 'installed' : 'not_installed'],
            'ok' => $pdoOk,
            'blocking' => true,
            'fail_key' => 'check_pdo_fail',
        ];

        $mbOk = extension_loaded('mbstring');
        $results[] = [
            'name_key' => 'check_mbstring',
            'current' => ['key' => $mbOk ? 'installed' : 'not_installed'],
            'ok' => $mbOk,
            'blocking' => true,
            'fail_key' => 'check_mbstring_fail',
        ];

        $jsonOk = extension_loaded('json');
        $results[] = [
            'name_key' => 'check_json',
            'current' => ['key' => $jsonOk ? 'installed' : 'not_installed'],
            'ok' => $jsonOk,
            'blocking' => true,
            'fail_key' => 'check_json_fail',
        ];

        $sslOk = extension_loaded('openssl');
        $results[] = [
            'name_key' => 'check_openssl',
            'current' => ['key' => $sslOk ? 'installed' : 'not_installed'],
            'ok' => $sslOk,
            'blocking' => true,
            'fail_key' => 'check_openssl_fail',
        ];

        $pcntlOk = extension_loaded('pcntl');
        $results[] = [
            'name_key' => 'check_pcntl',
            'current' => ['key' => $pcntlOk ? 'installed' : 'not_installed'],
            'ok' => $pcntlOk,
            'blocking' => true,
            'fail_key' => 'check_pcntl_fail',
        ];

        $gdOk = extension_loaded('gd');
        $results[] = [
            'name_key' => 'check_gd',
            'current' => ['key' => $gdOk ? 'installed' : 'not_installed'],
            'ok' => $gdOk,
            'blocking' => false,
            'fail_key' => 'check_gd_fail',
        ];

        $xmlOk = extension_loaded('xml');
        $results[] = [
            'name_key' => 'check_xml',
            'current' => ['key' => $xmlOk ? 'installed' : 'not_installed'],
            'ok' => $xmlOk,
            'blocking' => false,
            'fail_key' => 'check_xml_fail',
        ];

        $redisOk = extension_loaded('redis');
        $results[] = [
            'name_key' => 'check_redis',
            'current' => ['key' => $redisOk ? 'installed' : 'not_installed'],
            'ok' => $redisOk,
            'blocking' => false,
            'fail_key' => 'check_redis_fail',
        ];

        $adminRuntime = dirname(__DIR__) . '/admin/runtime';
        $serviceRuntime = dirname(__DIR__) . '/service/runtime';
        $writableOk = true;
        $writableDirs = [];

        if (!is_dir($adminRuntime)) {
            @mkdir($adminRuntime, 0755, true);
        }
        if (is_dir($adminRuntime) && is_writable($adminRuntime)) {
            $writableDirs[] = 'admin/runtime';
        } else {
            $writableOk = false;
        }

        if (!is_dir($serviceRuntime)) {
            @mkdir($serviceRuntime, 0755, true);
        }
        if (is_dir($serviceRuntime) && is_writable($serviceRuntime)) {
            $writableDirs[] = 'service/runtime';
        } else {
            $writableOk = false;
        }

        $results[] = [
            'name_key' => 'check_dirs',
            'current' => $writableOk
                ? ['key' => 'check_dirs_current_ok', 'params' => ['dirs' => implode(', ', $writableDirs)]]
                : ['key' => 'check_dirs_current_fail'],
            'ok' => $writableOk,
            'blocking' => true,
            'fail_key' => 'check_dirs_fail',
        ];

        $sqlOk = file_exists($this->sqlFile) && is_readable($this->sqlFile);
        $results[] = [
            'name_key' => 'check_sql',
            'current' => ['key' => $sqlOk ? 'exists' : 'missing'],
            'ok' => $sqlOk,
            'blocking' => true,
            'fail_key' => 'check_sql_fail',
        ];

        $envTemplateOk = is_readable($this->envTemplatePath('admin')) && is_readable($this->envTemplatePath('service'));
        $results[] = [
            'name_key' => 'check_env_template',
            'current' => ['key' => $envTemplateOk ? 'exists' : 'missing'],
            'ok' => $envTemplateOk,
            'blocking' => true,
            'fail_key' => 'check_env_template_fail',
        ];

        $this->envCheckResults = $results;
        return $results;
    }

    public function allEnvChecksPassed(): bool
    {
        foreach ($this->envCheckResults as $result) {
            if (!empty($result['blocking']) && !$result['ok']) {
                return false;
            }
        }
        return true;
    }

    // ── Step 2: 数据库连接测试 ──

    public function testDbConnection(string $host, int $port, string $database, string $username, string $password): array
    {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($database));
            $dbExists = (bool)$stmt->fetch();

            if (!$dbExists) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $dsnDb = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdoDb = new PDO($dsnDb, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdoDb->query('SELECT VERSION()')->fetchColumn();

            $stmt = $pdoDb->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = " . $pdoDb->quote($database) . " AND TABLE_NAME LIKE 'game_%'");
            $existingTables = (int)$stmt->fetchColumn();

            return [
                'success' => true,
                'version' => $version,
                'db_created' => !$dbExists,
                'existing_tables' => $existingTables,
            ];
        } catch (PDOException $e) {
            [$key, $params] = $this->parseDbError($e);
            return [
                'success' => false,
                'message_key' => $key,
                'message_params' => $params,
            ];
        }
    }

    /**
     * @return array{0: string, 1: array<string, string>} 文案键 + 插值参数
     */
    private function parseDbError(PDOException $e): array
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'SQLSTATE[HY000] [2002]')) {
            return ['db_err_conn', []];
        }
        if (str_contains($msg, 'Access denied')) {
            return ['db_err_auth', []];
        }
        if (str_contains($msg, 'Unknown database')) {
            return ['db_err_no_db', []];
        }
        return ['db_err_generic', ['error' => $msg]];
    }

    // ── Step 3-4: 执行安装 ──

    public function runInstall(array $dbConfig, string $adminUsername, string $adminPassword, bool $configureService = false, array $serviceDbConfig = [], bool $installTestData = false, ?callable $onProgress = null): array
    {
        @set_time_limit(120);
        $steps = [];
        // 进度上报: 每完成一步回调一次 ($key, $completed, $total)，供前端弹框进度条流式展示
        $total = 4 + (int) $installTestData + (int) $configureService;
        $emit = static function (string $key, int $completed) use ($onProgress, $total): void {
            if ($onProgress !== null) {
                $onProgress($key, $completed, $total);
            }
        };

        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // 存量库（已有 game_ 表）先执行迁移补结构，保证 install.sql 种子列引用可用
            if ($this->countTables($pdo) > 0) {
                foreach (glob($this->installDir . '/migrations/*.sql') as $migrationFile) {
                    try {
                        $pdo->exec((string) file_get_contents($migrationFile));
                    } catch (PDOException $e) {
                        // 42S21/42S22 = 重复加列/缺列的迁移重放，幂等忽略
                        if (!in_array($e->getCode(), ['42S21', '42S22'], true)) {
                            throw $e;
                        }
                    }
                }
            }

            $sql = file_get_contents($this->sqlFile);
            if (!$sql) {
                return ['success' => false, 'message_key' => 'install_fail_sql_unreadable', 'steps' => $steps];
            }

            $pdo->exec($sql);
            $tableCount = $this->countTables($pdo);
            $steps[] = ['name_key' => 'step_db_init', 'ok' => true, 'message_key' => 'step_db_init_ok', 'params' => ['count' => $tableCount]];
            $emit('step_db_init', count($steps));

            $adminId = $this->snowflakeId(1);
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare('INSERT INTO `game_admin_user` (`id`, `username`, `password`, `real_name`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
            $stmt->execute([$adminId, $adminUsername, $hashedPassword, '']);

            $stmt = $pdo->prepare('INSERT INTO `game_admin_user_role` (`user_id`, `role_id`) VALUES (?, 10000000000000001)');
            $stmt->execute([$adminId]);

            $steps[] = ['name_key' => 'step_admin', 'ok' => true, 'message_key' => 'step_admin_ok', 'params' => ['username' => $adminUsername]];
            $emit('step_admin', count($steps));

            if ($installTestData) {
                $testDataSql = @file_get_contents($this->installDir . '/test-data.sql');
                if ($testDataSql === false) {
                    throw new \RuntimeException('无法读取 install/test-data.sql');
                }
                $pdo->exec($testDataSql);
                $steps[] = ['name_key' => 'step_test_data', 'ok' => true, 'message_key' => 'step_test_data_ok'];
                $emit('step_test_data', count($steps));
            }

            $jwtSecret = $this->randomString(64);
            $serviceJwtSecret = $this->randomString(64);
            $hashidsSalt = $this->randomString(32);
            $hashidsAltSalt = $this->randomString(32);
            $encryptionKey = $this->randomString(32);
            $encryptableKey = $this->randomString(32);

            // 覆盖前先备份已有 .env（原备份时机在写入之后，备份到的是新内容，等于没备份）
            $this->backupEnvFiles();

            $this->writeEnvFromTemplate('admin', [
                'DB_HOST' => (string) $dbConfig['host'],
                'DB_PORT' => (string) $dbConfig['port'],
                'DB_DATABASE' => (string) $dbConfig['database'],
                'DB_USERNAME' => (string) $dbConfig['username'],
                'DB_PASSWORD' => (string) $dbConfig['password'],
                'ADMIN_JWT_SECRET_KEY' => $jwtSecret,
                'HASHIDS_SALT' => $hashidsSalt,
                'HASHIDS_ALT_SALT' => $hashidsAltSalt,
                'ENCRYPTION_KEY' => $encryptionKey,
                'ENCRYPTABLE_KEY' => $encryptableKey,
            ]);
            $steps[] = ['name_key' => 'step_admin_env', 'ok' => true, 'message_key' => 'step_admin_env_ok'];
            $emit('step_admin_env', count($steps));

            if ($configureService) {
                $svcDb = $serviceDbConfig ?: $dbConfig;
                // HASHIDS_SALT / 加密密钥与 admin 保持一致: 两侧共用 salt，hashid 才能跨服务直通
                $this->writeEnvFromTemplate('service', [
                    'DB_HOST' => (string) $svcDb['host'],
                    'DB_PORT' => (string) $svcDb['port'],
                    'DB_DATABASE' => (string) $svcDb['database'],
                    'DB_USERNAME' => (string) $svcDb['username'],
                    'DB_PASSWORD' => (string) $svcDb['password'],
                    'JWT_SECRET' => $serviceJwtSecret,
                    'SERVICE_JWT_SECRET_KEY' => $serviceJwtSecret,
                    'HASHIDS_SALT' => $hashidsSalt,
                    'HASHIDS_ALT_SALT' => $hashidsAltSalt,
                    'ENCRYPTION_KEY' => $encryptionKey,
                    'ENCRYPTABLE_KEY' => $encryptableKey,
                ]);
                $steps[] = ['name_key' => 'step_service_env', 'ok' => true, 'message_key' => 'step_service_env_ok'];
                $emit('step_service_env', count($steps));
            }

            file_put_contents($this->lockFile, json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'admin_username' => $adminUsername,
                'version' => '1.0.0',
                'test_data' => $installTestData,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $steps[] = ['name_key' => 'step_lock', 'ok' => true, 'message_key' => 'step_lock_ok'];
            $emit('step_lock', count($steps));

            return ['success' => true, 'steps' => $steps, 'admin_id' => $adminId];
        } catch (PDOException $e) {
            return ['success' => false, 'message_key' => 'install_fail_db', 'params' => ['error' => $e->getMessage()], 'steps' => $steps ?? []];
        } catch (\Throwable $e) {
            return ['success' => false, 'message_key' => 'install_fail_generic', 'params' => ['error' => $e->getMessage()], 'steps' => $steps ?? []];
        }
    }

    // ── 工具方法 ──

    private function countTables(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'game_%'");
        return (int)$stmt->fetchColumn();
    }

    private function snowflakeId(int $workerId = 1): string
    {
        $timestamp = (int)(microtime(true) * 1000) - 1700000000000;
        $datacenterId = 1;
        static $sequence = 0;
        $sequence = ($sequence + 1) & 0xFFF;
        return (string)(($timestamp << 22) | ($datacenterId << 17) | ($workerId << 12) | $sequence);
    }

    private function randomString(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    private function envTemplatePath(string $app): string
    {
        return dirname(__DIR__) . '/' . $app . '/.env.example';
    }

    /**
     * 以仓库 .env.example 为模板生成 .env：仅替换 $values 里的键，其余内容（键/注释/顺序）逐字节保留
     */
    private function writeEnvFromTemplate(string $app, array $values): void
    {
        $content = @file_get_contents($this->envTemplatePath($app));
        if ($content === false) {
            throw new \RuntimeException("无法读取配置模板 {$app}/.env.example");
        }
        foreach ($values as $key => $value) {
            if (!preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
                // 模板缺键说明仓库模板已变更，fail-fast 而不是静默写出缺配置的 .env
                throw new \RuntimeException("配置模板 {$app}/.env.example 缺少键 {$key}");
            }
            $content = preg_replace_callback(
                '/^' . preg_quote($key, '/') . '=.*$/m',
                static fn (): string => $key . '=' . self::envValue($value),
                $content,
                1
            );
        }
        file_put_contents(dirname(__DIR__) . "/{$app}/.env", $content);
    }

    /**
     * phpdotenv v5 未加引号的值遇空格/#/$ 会被截断或插值，含这些字符时必须双引号包裹并转义
     */
    private static function envValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9._\/:@-]+$/', $value)) {
            return $value;
        }
        return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']) . '"';
    }

    private function backupEnvFiles(): void
    {
        $adminEnv = dirname(__DIR__) . '/admin/.env';
        $serviceEnv = dirname(__DIR__) . '/service/.env';
        if (file_exists($adminEnv)) {
            copy($adminEnv, dirname(__DIR__) . '/admin/.env.backup.' . date('YmdHis'));
        }
        if (file_exists($serviceEnv)) {
            copy($serviceEnv, dirname(__DIR__) . '/service/.env.backup.' . date('YmdHis'));
        }
    }
}
