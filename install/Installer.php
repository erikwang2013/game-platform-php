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
            'name' => 'PHP 版本',
            'current' => $phpVersion,
            'required' => '>= 8.1.0',
            'ok' => $phpOk,
            'message' => $phpOk ? '通过' : '需要 PHP 8.1 或更高版本',
        ];

        $pdoOk = extension_loaded('pdo_mysql');
        $results[] = [
            'name' => 'PDO MySQL',
            'current' => $pdoOk ? '已安装' : '未安装',
            'required' => '必须',
            'ok' => $pdoOk,
            'message' => $pdoOk ? '通过' : '请安装 pdo_mysql 扩展',
        ];

        $mbOk = extension_loaded('mbstring');
        $results[] = [
            'name' => 'MBString',
            'current' => $mbOk ? '已安装' : '未安装',
            'required' => '必须',
            'ok' => $mbOk,
            'message' => $mbOk ? '通过' : '请安装 mbstring 扩展',
        ];

        $jsonOk = extension_loaded('json');
        $results[] = [
            'name' => 'JSON',
            'current' => $jsonOk ? '已安装' : '未安装',
            'required' => '必须',
            'ok' => $jsonOk,
            'message' => $jsonOk ? '通过' : '请安装 json 扩展',
        ];

        $sslOk = extension_loaded('openssl');
        $results[] = [
            'name' => 'OpenSSL',
            'current' => $sslOk ? '已安装' : '未安装',
            'required' => '必须',
            'ok' => $sslOk,
            'message' => $sslOk ? '通过' : '请安装 openssl 扩展',
        ];

        $pcntlOk = extension_loaded('pcntl');
        $results[] = [
            'name' => 'PCNTL',
            'current' => $pcntlOk ? '已安装' : '未安装',
            'required' => '必须',
            'ok' => $pcntlOk,
            'message' => $pcntlOk ? '通过' : 'webman 框架需要 pcntl 扩展，请安装 php-pcntl',
        ];

        $gdOk = extension_loaded('gd');
        $results[] = [
            'name' => 'GD',
            'current' => $gdOk ? '已安装' : '未安装',
            'required' => '建议',
            'ok' => $gdOk,
            'message' => $gdOk ? '通过' : '验证码功能需要 GD 扩展（非阻塞）',
        ];

        $xmlOk = extension_loaded('xml');
        $results[] = [
            'name' => 'XML',
            'current' => $xmlOk ? '已安装' : '未安装',
            'required' => '建议',
            'ok' => $xmlOk,
            'message' => $xmlOk ? '通过' : 'Excel 导入导出需要 XML 扩展（非阻塞）',
        ];

        $redisOk = extension_loaded('redis');
        $results[] = [
            'name' => 'Redis',
            'current' => $redisOk ? '已安装' : '未安装',
            'required' => '建议',
            'ok' => $redisOk,
            'message' => $redisOk ? '通过' : '缓存/限流/Session 需要 Redis 扩展（非阻塞）',
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
            'name' => '目录权限',
            'current' => $writableOk ? '可写 (' . implode(', ', $writableDirs) . ')' : '部分不可写',
            'required' => '必须',
            'ok' => $writableOk,
            'message' => $writableOk ? '通过' : '请确保 admin/runtime 和 service/runtime 目录可写',
        ];

        $sqlOk = file_exists($this->sqlFile) && is_readable($this->sqlFile);
        $results[] = [
            'name' => '安装 SQL 文件',
            'current' => $sqlOk ? '存在' : '不存在',
            'required' => '必须',
            'ok' => $sqlOk,
            'message' => $sqlOk ? '通过' : 'install/install.sql 文件不存在或不可读',
        ];

        $this->envCheckResults = $results;
        return $results;
    }

    public function allEnvChecksPassed(): bool
    {
        foreach ($this->envCheckResults as $result) {
            if ($result['required'] === '必须' && !$result['ok']) {
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

            $stmt = $pdoDb->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = " . $pdoDb->quote($database) . " AND TABLE_NAME LIKE 'erik_%'");
            $existingTables = (int)$stmt->fetchColumn();

            return [
                'success' => true,
                'version' => $version,
                'db_created' => !$dbExists,
                'existing_tables' => $existingTables,
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => $this->parseDbError($e),
            ];
        }
    }

    private function parseDbError(PDOException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'SQLSTATE[HY000] [2002]')) {
            return '无法连接到数据库服务器，请检查主机地址和端口';
        }
        if (str_contains($msg, 'Access denied')) {
            return '数据库认证失败，请检查用户名和密码';
        }
        if (str_contains($msg, 'Unknown database')) {
            return '数据库不存在且无法自动创建';
        }
        return '数据库连接失败: ' . $msg;
    }

    // ── Step 3-4: 执行安装 ──

    public function runInstall(array $dbConfig, string $adminUsername, string $adminPassword, bool $configureService = false, array $serviceDbConfig = []): array
    {
        @set_time_limit(120);
        $steps = [];

        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $sql = file_get_contents($this->sqlFile);
            if (!$sql) {
                return ['success' => false, 'message' => '无法读取 install.sql 文件'];
            }

            $pdo->exec($sql);
            $tableCount = $this->countTables($pdo);
            $steps[] = ['name' => '数据库初始化', 'ok' => true, 'message' => "成功创建 {$tableCount} 张数据表并导入种子数据"];

            $adminId = $this->snowflakeId(1);
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare('INSERT INTO `erik_admin_user` (`id`, `username`, `password`, `real_name`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
            $stmt->execute([$adminId, $adminUsername, $hashedPassword]);

            $stmt = $pdo->prepare('INSERT INTO `erik_admin_user_role` (`user_id`, `role_id`) VALUES (?, 10000000000000001)');
            $stmt->execute([$adminId]);

            $steps[] = ['name' => '管理员账户', 'ok' => true, 'message' => "创建管理员 {$adminUsername} 并关联超级管理员角色"];

            $jwtSecret = $this->randomString(64);
            $serviceJwtSecret = $this->randomString(64);
            $hashidsSalt = $this->randomString(32);
            $encryptionKey = $this->randomString(32);
            $openSearchPass = $this->randomString(16);
            $clickHousePass = $this->randomString(16);

            $this->writeAdminEnv($dbConfig, $jwtSecret, $hashidsSalt, $encryptionKey, $openSearchPass);
            $steps[] = ['name' => 'Admin .env 配置', 'ok' => true, 'message' => '已写入 admin/.env'];

            if ($configureService) {
                $svcDb = $serviceDbConfig ?: $dbConfig;
                $this->writeServiceEnv($svcDb, $serviceJwtSecret, $hashidsSalt, $encryptionKey, $openSearchPass, $clickHousePass);
                $steps[] = ['name' => 'Service .env 配置', 'ok' => true, 'message' => '已写入 service/.env'];
            }

            file_put_contents($this->lockFile, json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'admin_username' => $adminUsername,
                'version' => '1.0.0',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $steps[] = ['name' => '安装锁定', 'ok' => true, 'message' => '已生成 install.lock，防止重复安装'];

            $this->backupEnvFiles();

            return ['success' => true, 'steps' => $steps, 'admin_id' => $adminId];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '数据库操作失败: ' . $e->getMessage(), 'steps' => $steps ?? []];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '安装失败: ' . $e->getMessage(), 'steps' => $steps ?? []];
        }
    }

    // ── 工具方法 ──

    private function countTables(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'erik_%'");
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

    private function writeAdminEnv(array $db, string $jwtSecret, string $hashidsSalt, string $encryptionKey, string $openSearchPass): void
    {
        $envFile = dirname(__DIR__) . '/admin/.env';
        file_put_contents($envFile, $this->buildAdminEnvContent($db, $jwtSecret, $hashidsSalt, $encryptionKey, $openSearchPass));
    }

    private function buildAdminEnvContent(array $db, string $jwtSecret, string $hashidsSalt, string $encryptionKey, string $openSearchPass): string
    {
        $encryptionKey32 = str_pad($encryptionKey, 32, '0');
        return <<<EOF
# ============================================================
# 开放管理后台 — 环境变量（由安装向导自动生成）
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# ============================================================

APP_NAME=开放管理后台
APP_DEBUG=false
APP_URL=http://localhost:8787

JWT_SECRET={$jwtSecret}
JWT_ALGORITHM=HS256
JWT_TTL=7200
JWT_REFRESH_TTL=1209600
JWT_ISSUER=game-platform
JWT_AUDIENCE=game-platform

HASHIDS_SALT={$hashidsSalt}
HASHIDS_ALT_SALT={$hashidsSalt}_alt

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_START_TIMESTAMP=1700000000000

ENCRYPTION_KEY={$encryptionKey32}
ENCRYPTION_CIPHER=AES-256-CBC
ENCRYPTION_IV=

ENCRYPTABLE_KEY={$encryptionKey32}-db
ENCRYPTABLE_CIPHER=AES-256-CBC
ENCRYPTION_PREVIOUS_KEYS=

SCOUT_DRIVER=opensearch
SCOUT_HOSTS=http://localhost:9200
SCOUT_PREFIX=erik_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true

OPENSEARCH_HTTP_HOST=http://localhost:37831
OPENSEARCH_USERNAME=admin
OPENSEARCH_PASSWORD={$openSearchPass}
OPENSEARCH_INDEX_PREFIX=game_
OPENSEARCH_SSL_VERIFICATION=false
OPENSEARCH_SSL_CERT=
OPENSEARCH_SSL_KEY=
OPENSEARCH_RETRIES=2
OPENSEARCH_CONNECTION_TIMEOUT=10
OPENSEARCH_TIMEOUT=30

POSTER_IMAGE_DRIVER=auto
POSTER_IMAGE_QUALITY=90
POSTER_CAPTCHA_STORAGE=auto
POSTER_CAPTCHA_TTL=300
POSTER_CAPTCHA_MAX_ATTEMPTS=3
POSTER_CAPTCHA_DIFFICULTY=medium

DB_CONNECTION=mysql
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD={$db['password']}

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0

ADMIN_JWT_SECRET_KEY={$jwtSecret}
JWT_DEFAULT_EXPIRE=7200
JWT_REFRESH_EXPIRE=1209600
EOF;
    }

    private function writeServiceEnv(array $db, string $jwtSecret, string $hashidsSalt, string $encryptionKey, string $openSearchPass, string $clickHousePass): void
    {
        $envFile = dirname(__DIR__) . '/service/.env';
        file_put_contents($envFile, $this->buildServiceEnvContent($db, $jwtSecret, $hashidsSalt, $encryptionKey, $openSearchPass, $clickHousePass));
    }

    private function buildServiceEnvContent(array $db, string $jwtSecret, string $hashidsSalt, string $encryptionKey, string $openSearchPass, string $clickHousePass): string
    {
        $encryptionKey32 = str_pad($encryptionKey, 32, '0');
        return <<<EOF
# ============================================================
# 全球游戏聚合平台 — C端业务端环境变量（由安装向导自动生成）
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# ============================================================

APP_ENV=local
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD={$db['password']}

JWT_SECRET={$jwtSecret}
SERVICE_JWT_SECRET_KEY={$jwtSecret}
JWT_TTL=7200
JWT_REFRESH_TTL=1209600

HASHIDS_SALT={$hashidsSalt}
HASHIDS_ALPHABET=abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=2

ENCRYPTION_KEY={$encryptionKey32}
ENCRYPTION_CIPHER=AES-256-CBC
ENCRYPTABLE_KEY={$encryptionKey32}-db
ENCRYPTABLE_CIPHER=AES-256-CBC

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_DB={$db['database']}
CLICKHOUSE_USER=default
CLICKHOUSE_PASS={$clickHousePass}

OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_GOOGLE_REDIRECT_URI=
OAUTH_FACEBOOK_CLIENT_ID=
OAUTH_FACEBOOK_CLIENT_SECRET=
OAUTH_FACEBOOK_REDIRECT_URI=
OAUTH_APPLE_CLIENT_ID=
OAUTH_APPLE_CLIENT_SECRET=
OAUTH_APPLE_REDIRECT_URI=

STRIPE_WEBHOOK_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_VERIFY_URL=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_MODE=sandbox
FCM_SERVER_KEY=
FCM_SERVICE_ACCOUNT_JSON=
APNS_KEY_ID=
APNS_TEAM_ID=
APNS_KEY_FILE=
APNS_TOPIC=
APNS_MODE=sandbox
HUAWEI_APP_ID=
HUAWEI_APP_SECRET=

CORS_ORIGIN=*

SCOUT_DRIVER=opensearch
SCOUT_HOSTS=http://localhost:9200
SCOUT_PREFIX=erik_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true

OPENSEARCH_HTTP_HOST=http://localhost:37831
OPENSEARCH_USERNAME=admin
OPENSEARCH_PASSWORD={$openSearchPass}
OPENSEARCH_INDEX_PREFIX=game_
OPENSEARCH_SSL_VERIFICATION=false
OPENSEARCH_SSL_CERT=
OPENSEARCH_SSL_KEY=
OPENSEARCH_RETRIES=2
OPENSEARCH_CONNECTION_TIMEOUT=10
OPENSEARCH_TIMEOUT=30
EOF;
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
