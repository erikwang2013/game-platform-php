<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use Erikwang2013\Encryptable\Config\EnvEncryptableConfig;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Encryption;

/**
 * 游戏 api_secret 空值回填 —— 独立运行，不引导框架、不读 .env。
 *
 * ## 为什么需要
 * 回调与 SDK 令牌的 HMAC 都以 game.api_secret 为密钥。空密钥时
 * hash_hmac('sha256', $str, '') 人人可算，签名校验等于没有；两侧中间件
 * （ProviderAuth / SdkSessionAuth）现已改为对空密钥 fail-closed 401，
 * 于是「历史遗留的空密钥游戏」从「不安全但能用」变成「直接不可用」。
 * 而 install.sql 里 api_secret 默认就是 ''，且演示数据用 INSERT IGNORE 播种，
 * 已存在的行再跑一次种子也不会被修正 —— 必须单独回填。
 *
 * ## 为什么 SQL 迁移替代不了（原方案是 SQL，已废）
 * 1. 空值有两种形态：明文 ''，以及「空串的密文」。模型读取走 Encryptable cast
 *    （Encryption::php()，strict=false），后者解密回来也是空串，
 *    中间件的 `(string) $game->api_secret === ''` 同样判空 —— 对应用而言两种都是空。
 *    但 SQL 只能 WHERE api_secret = ''，看不见密文形态；而密文形态又无法用长度/
 *    形状筛出来（空串密文 88 字符，1~2 字符的真实密钥同样是 88 字符）。
 * 2. SQL 也写不出正确的密文：要落库成模型能读回的值，必须用应用的密钥与算法加密。
 * 3. 弱随机：UUID() 逐行求值虽互不相同，但实测三条只剩后 24/32 个 hex 字符有区别
 *    （同毫秒时高位相同），远不如 bin2hex(random_bytes(32))。
 *
 * ## 加解密一致性（写入前必读）
 * 密钥/算法一律取自环境变量 ENCRYPTION_KEY / ENCRYPTION_CIPHER —— 与应用内
 * Encryptable 的 env 回退路径（EnvEncryptableConfig）读的是同一对变量，因此
 * 脚本写出的密文与模型自己写的**逐字节同构**。
 * 两个都必须显式给出、不给默认值：本仓 admin 与 service 解析出的 cipher 目前**不同**
 * （同一 ENCRYPTION_KEY 下，admin 因 .env 设了 ENCRYPTION_CIPHER 得到 AES-256-CBC，
 * service 缺该项回退到 aes-256-gcm），而 V1(非AEAD) 与 V2(AEAD) 载荷互不可解，
 * 解密失败时 strict=false 会把密文原样当成明文返回 —— 即「写进去看着非空、
 * 读出来却永远验不过 HMAC」且不报任何错。故此处拒绝默认值：
 * 写之前先确认 ENCRYPTION_CIPHER 与**读取方**应用一致，并核对脚本打印的
 * key_sha256/cipher 与该应用 .env 的取值。
 *
 * ## 判别与写入
 *   扫描 type IN ('self','embedded') 的行，逐行用模型同款解密路径取「有效值」：
 *     原始为 ''                       → 空，待修
 *     原始非空且看起来是密文、但解不开 → **中止**（密钥或算法不匹配的硬信号，绝不当成空）
 *     原始非空且能解开为 ''            → 空，待修
 *     其余（能解开的非空、明文常量）    → 已配置，不动
 *   待修行写入 bin2hex(random_bytes(32))，经同一 Encrypter 加密（与 admin 生成的一致）。
 *   third_party 一行不碰：其密钥由对方提供，平台代生成会让对方验签失败。
 *
 * ## 幂等
 * 回填后该行的有效值不再为空，重复执行待修恒为 0，不产生任何写入。
 * 不做备份表：原值为空，没有可丢失的内容；新值每次重新随机，无法也不需要还原。
 *
 * ## 用法
 *   # 自检：不连库、不读 .env，只验加解密路径与判别语义
 *   ENCRYPTION_KEY=<32 字节> ENCRYPTION_CIPHER=aes-256-cbc \
 *     php scripts/migrate-game-api-secret-backfill.php --self-check
 *
 *   # dry-run（默认）：只统计与样例，不写库
 *   ENCRYPTION_KEY=<与读取方一致> ENCRYPTION_CIPHER=<与读取方一致> DB_DATABASE=game-platform \
 *     php scripts/migrate-game-api-secret-backfill.php
 *
 *   # 真正写库
 *   ENCRYPTION_KEY=... ENCRYPTION_CIPHER=... DB_DATABASE=game-platform \
 *     php scripts/migrate-game-api-secret-backfill.php --apply
 *
 * 连接信息一律取自环境变量（DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD），
 * DB_DATABASE 无默认值，必须显式给出，避免误连别的库。
 */

const TABLE = 'game_game';
const SAMPLE_LIMIT = 5;

/** 只有自研/内嵌游戏的密钥由平台生成；第三方游戏的密钥由对方提供，代生成会让对方验签失败 */
const TYPES = ['self', 'embedded'];

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function usage(): void
{
    fwrite(STDERR, <<<'TXT'
用法: php scripts/migrate-game-api-secret-backfill.php [--apply] [--self-check] [--vendor=路径]

  --apply          真正写库（默认 dry-run，只统计不写）
  --self-check     不连库，自检加解密路径与「空」的判别语义
  --vendor=路径    指定 vendor/autoload.php（默认 <仓库根>/admin/vendor/autoload.php，
                   只从中取 Encryptable 类，不引导框架、不读 .env）

必填环境变量:
  DB_DATABASE       目标库名（无默认值，避免误连别的库）
  ENCRYPTION_KEY    与读取方应用一致的密钥
  ENCRYPTION_CIPHER 与读取方应用一致的算法（无默认值：admin 与 service 解析结果不同，
                    默认值会让写出的密文在读取方静默解不开）

可选环境变量: DB_HOST DB_PORT DB_USERNAME DB_PASSWORD
TXT);
    fwrite(STDERR, "\n");
}

/**
 * 构造 Encrypter：密钥/算法显式给定，与 Encryptable cast 调用的 Encryption::php()
 * 同一套加解密实现（PHPEncrypter），只是把配置换成调用方给的值。
 *
 * 不用 Encryption::configure()：那是 encryptable 较新版本才有的入口，本仓 vendor 锁在
 * v2.0.3（无 ArrayEncryptableConfig 类）会直接 fatal；setFallbackConfig 在前后两版都在。
 *
 * 注意 setFallbackConfig 改的是进程级静态配置（并会清掉已解析的实例）：本脚本不引导框架、
 * 内置的这个 Encrypter 只用于本进程，故无副作用；别把它 require 进已引导的应用里用。
 */
function makeEncrypter(string $key, string $cipher): Encryption
{
    $config = new class($key, $cipher) implements EncryptableConfigContract {
        public function __construct(private string $key, private string $cipher)
        {
        }

        public function getKey(): ?string
        {
            return $this->key;
        }

        public function getCipher(): ?string
        {
            return $this->cipher;
        }

        /** 轮换遗留密钥沿用应用的同名环境变量，读旧密文时才能用上 */
        public function getPreviousKeys(): array
        {
            return (new EnvEncryptableConfig())->getPreviousKeys();
        }
    };

    Encryption::setFallbackConfig($config);

    return Encryption::php();
}

/** 校验环境变量后构造 Encrypter（KEY/CIPHER 必填，理由见文件头「加解密一致性」） */
function buildEncrypter(): Encryption
{
    $key    = (string) (getenv('ENCRYPTION_KEY') ?: '');
    $cipher = (string) (getenv('ENCRYPTION_CIPHER') ?: '');

    if ($key === '') {
        fail('必须显式设置 ENCRYPTION_KEY（与读取方应用 .env 的 ENCRYPTION_KEY 一致）');
    }
    if ($cipher === '') {
        fail('必须显式设置 ENCRYPTION_CIPHER（admin 解析 AES-256-CBC、service 回退 aes-256-gcm，二者互不可解，故无安全默认值）');
    }

    return makeEncrypter($key, $cipher);
}

/** 不连库的自检：验证「写出去的能读回来」与「三种空/非空的判别结果」 */
function selfCheck(string $vendor): void
{
    require $vendor;

    // 恰 32 字节（aes-256 系列要求），仅自检用，非任何环境的真实密钥
    $testKey = str_repeat('0123456789abcdef', 2);
    $gcm = makeEncrypter($testKey, 'aes-256-gcm');
    $cbc = makeEncrypter($testKey, 'aes-256-cbc');

    $secret  = bin2hex(random_bytes(32));
    $sealed  = $gcm->encrypt($secret);
    $emptied = $gcm->encrypt('');

    $cases = [
        '随机密钥往返（写出去能读回来）'      => [$secret, (string) $gcm->decrypt($sealed)],
        '空串的密文解开是空串'                => ['', (string) $gcm->decrypt($emptied)],
        '明文常量原样读回（非空，不该被动）'  => ['demo-secret-lucky-spin-0001', (string) $gcm->decrypt('demo-secret-lucky-spin-0001')],
    ];

    $failed = 0;
    foreach ($cases as $name => [$expected, $actual]) {
        $ok = $expected === $actual;
        $failed += $ok ? 0 : 1;
        printf("%s %s\n", $ok ? 'OK  ' : 'FAIL', $name);
    }

    // 判别语义：空串与空串密文都判空；明文常量判非空；空串密文必须被认出是密文
    $shapes = [
        '原始为空串 → 判空'                     => ['', true],
        '空串的密文 → 解出来仍是空 → 判空'      => [$emptied, true],
        '明文常量 → 判非空（不动）'             => ['demo-secret-lucky-spin-0001', false],
        '64 位 hex 明文密钥 → 判非空（不动）'   => [$secret, false],
    ];
    foreach ($shapes as $name => [$raw, $shouldBeEmpty]) {
        $isEncrypted = Encryption::isEncrypted($raw);
        $effective   = $raw === '' ? '' : ($isEncrypted ? (string) $gcm->decrypt($raw) : $raw);
        $broken      = $raw !== '' && $isEncrypted && $effective === $raw;
        $isEmpty     = $effective === '';
        $ok          = ! $broken && $isEmpty === $shouldBeEmpty;
        $failed     += $ok ? 0 : 1;
        printf("%s %s（isEncrypted=%s 判空=%s 解密失败=%s）\n",
            $ok ? 'OK  ' : 'FAIL', $name, var_export($isEncrypted, true), var_export($isEmpty, true), var_export($broken, true));
    }

    // 跨算法必须解不开：这正是「cipher 用错」时的现场，也是中止闸的触发条件
    $cross = $cbc->decrypt($sealed);
    $ok    = $cross === $sealed;
    $failed += $ok ? 0 : 1;
    printf("%s 换算法解密失败且原样返回（中止闸的判据）\n", $ok ? 'OK  ' : 'FAIL');

    if ($failed > 0) {
        fail("自检失败 {$failed} 项");
    }
    echo "自检通过\n";
}

/** @return array{key:string,cipher:string} */
function argValueOr(array $args, string $name, string $default): string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return $default;
}

// ---------------------------------------------------------------- 参数与环境

$args = array_slice($argv, 1);
$apply = false;
$selfCheckFlag = false;
$vendorOpt = '';

foreach ($args as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--self-check') {
        $selfCheckFlag = true;
    } elseif (str_starts_with($arg, '--vendor=')) {
        $vendorOpt = substr($arg, strlen('--vendor='));
    } elseif ($arg === '--help' || $arg === '-h') {
        usage();
        exit(0);
    } else {
        usage();
        fail("未知参数: {$arg}");
    }
}

$vendor = $vendorOpt !== '' ? $vendorOpt : dirname(__DIR__) . '/admin/vendor/autoload.php';

if ($selfCheckFlag) {
    if (! is_file($vendor)) {
        fail("找不到 vendor autoload: {$vendor}");
    }
    selfCheck($vendor);
    exit(0);
}

if (! is_file($vendor)) {
    fail("找不到 vendor autoload: {$vendor}（用 --vendor= 指定其它应用的 vendor/autoload.php）");
}
require $vendor;

// ---------------------------------------------------------------- 连接

$dbname = (string) (getenv('DB_DATABASE') ?: '');
if ($dbname === '') {
    fail('必须显式设置 DB_DATABASE（无默认值，避免误连别的库）');
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    (int) (getenv('DB_PORT') ?: 3306),
    $dbname
);

try {
    $pdo = new PDO($dsn, (string) (getenv('DB_USERNAME') ?: ''), (string) (getenv('DB_PASSWORD') ?: ''), [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES  => false,
        PDO::ATTR_STRINGIFY_FETCHES => true,
    ]);
} catch (Throwable $e) {
    fail('连接失败: ' . $e->getMessage());
}

$enc = buildEncrypter();

echo '目标库: ' . $dbname . "\n";
echo '写入 cipher: ' . getenv('ENCRYPTION_CIPHER') . '  key_sha256: '
    . substr(hash('sha256', (string) getenv('ENCRYPTION_KEY')), 0, 12)
    . ' (len=' . strlen((string) getenv('ENCRYPTION_KEY')) . ")\n";
echo "请核对上面两值与该库读取方应用 .env 的 ENCRYPTION_KEY / ENCRYPTION_CIPHER 一致\n";

// ---------------------------------------------------------------- 扫描（只读）

$in     = implode(',', array_fill(0, count(TYPES), '?'));
$stmt   = $pdo->prepare('SELECT id, type, api_secret FROM ' . TABLE . " WHERE type IN ({$in}) ORDER BY id");
$stmt->execute(TYPES);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$scanned  = count($rows);
$pending  = [];
$broken   = [];
$untouched = (int) $pdo->query('SELECT COUNT(*) FROM ' . TABLE . " WHERE type NOT IN ('" . implode("','", TYPES) . "')")->fetchColumn();

foreach ($rows as $row) {
    $raw = (string) $row['api_secret'];

    if ($raw === '') {
        $pending[] = ['id' => $row['id'], 'type' => $row['type'], 'raw' => $raw, 'shape' => '明文空串'];

        continue;
    }

    if (! Encryption::isEncrypted($raw)) {
        continue; // 明文（含演示常量）：已配置，不动
    }

    $effective = (string) $enc->decrypt($raw);

    if ($effective === $raw) {
        // 看起来是密文但解不开：密钥/算法不匹配。绝不能当成空去覆盖，否则会把
        // 一个「读不出来但可能存在」的密钥抹掉，且写出的新密文读取方同样解不开。
        $broken[] = ['id' => $row['id'], 'type' => $row['type']];

        continue;
    }

    if ($effective === '') {
        $pending[] = ['id' => $row['id'], 'type' => $row['type'], 'raw' => $raw, 'shape' => '密文空（长度 ' . strlen($raw) . '）'];
    }
}

echo '扫描: ' . $scanned . " 行（type IN ('" . implode("','", TYPES) . "')）\n";
echo '待修: ' . count($pending) . " 行（解密后为空）\n";
if (! $apply) {
    echo "实修: 0 行（dry-run 不写库）\n";
}
// --apply 时「实修」留到真正写完再报：中止闸可能在这之后拦下，提前报数会谎报写入
echo '未扫描（third_party 等，不动）: ' . $untouched . " 行\n";

foreach (array_slice($pending, 0, SAMPLE_LIMIT) as $p) {
    echo "  待修 id={$p['id']} type={$p['type']} 形态={$p['shape']}\n";
}

if ($broken !== []) {
    fwrite(STDERR, "\n");
    foreach (array_slice($broken, 0, SAMPLE_LIMIT) as $b) {
        fwrite(STDERR, "  id={$b['id']} type={$b['type']} 看起来是密文但解不开\n");
    }
    fail('存在 ' . count($broken) . ' 行密文无法解密：ENCRYPTION_KEY/ENCRYPTION_CIPHER 与写入方不一致，'
        . '先在应用侧统一配置再回填（本次未写任何数据）');
}

if (! $apply) {
    echo "\nDRY-RUN：未写任何数据。确认无误后加 --apply 再跑一次。\n";
    exit(0);
}

if ($pending === []) {
    echo "\n实修: 0 行（没有需要回填的行）\n";
    exit(0);
}

// ---------------------------------------------------------------- 写库

$sealed = []; // 先生成好全部新值：random_bytes 失败不该发生在事务中途
foreach ($pending as $i => $p) {
    $secret = bin2hex(random_bytes(32));
    $value  = $enc->encrypt($secret);
    if (! is_string($value) || $value === '' || (string) $enc->decrypt($value) !== $secret) {
        fail("第 {$i} 个待修行的密文自检失败，未写任何数据");
    }
    $sealed[] = ['id' => $p['id'], 'type' => $p['type'], 'raw' => $p['raw'], 'value' => $value];
}

// 以读到的原值为条件做乐观锁：并发下已被别处改过的行自动跳过，绝不用随机值覆盖已知内容
$update = $pdo->prepare('UPDATE ' . TABLE . ' SET api_secret = ? WHERE id = ? AND type = ? AND api_secret = ?');

$done = 0;
$pdo->beginTransaction();
try {
    foreach ($sealed as $s) {
        $update->execute([$s['value'], $s['id'], $s['type'], $s['raw']]);
        $done += $update->rowCount();
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('写库失败已回滚: ' . $e->getMessage());
}

echo "\n实修: {$done} 行（已加密落库；third_party 未触碰）\n";
echo "提示：新密钥为随机生成，需在游戏侧同步配置后方可用于回调验签。\n";
