<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 历史兑换记录（direction=out）金额语义迁移脚本 —— 独立运行，无框架依赖，不读 .env。
 *
 * ## 判别依据（为什么必须显式给 --before）
 * 修复前写入的 out 行是旧语义：platform_amount = 请求回显的游戏币数、
 * game_amount = 平台币折算额、spread_fee = 平台币点差。
 * 修复后：game_amount = 游戏币数、platform_amount = 折算额 − 点差。
 * 新旧两版写进同一批列，**从数值本身无法区分**（汇率 1:1 时两版数值完全相同），
 * 所以脚本不能靠猜：必须由调用者给出「修复上线时刻」--before，只迁移
 * created_at 严格早于它的 out 行。给晚了会漏，给早了会把修复后的行二次换算，
 * 故该参数没有默认值：缺失时 dry-run 只统计规模，--apply 直接拒绝。
 *
 * ## 单行换算（bcmath，scale 8；两个输入都是 DECIMAL(18,4)，差值是精确的 4 位小数）
 *   game_amount_new     = platform_amount_old                （游戏币数）
 *   platform_amount_new = game_amount_old − spread_fee_old    （折算额 − 点差）
 *   spread_fee                                 不变（本来就是平台币）
 *
 * ## 幂等
 * 迁移前把原行整行复制进备份表 game_exchange_record_bak（PK=id，含 migrated_at），
 * 后续运行以「LEFT JOIN bak 且 bak.id IS NULL」筛选，已迁移的行自动跳过，重复执行零副作用。
 * 若要重跑，必须先从 bak 恢复原行并删掉 bak 中的对应行，否则会被幂等逻辑挡住。
 *
 * ## 用法
 *   # 自检：不连库，只验换算函数（交付/评审时跑这个）
 *   php scripts/migrate-exchange-out-record-semantics.php --self-check
 *
 *   # dry-run（默认）：只打印将受影响的行数 + 样例，不写库
 *   DB_DATABASE=game-platform php scripts/migrate-exchange-out-record-semantics.php \
 *     --before='2026-09-20 00:00:00'
 *
 *   # 真正写库（先备份到 _bak，再分批事务更新；需在终端输入 MIGRATE 确认）
 *   DB_DATABASE=game-platform php scripts/migrate-exchange-out-record-semantics.php \
 *     --before='2026-09-20 00:00:00' --apply
 *
 * 连接信息一律取自环境变量（DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD），
 * DB_DATABASE 无默认值，必须显式给出，避免误连别的库。
 */

const SCALE = 8;
const BATCH_SIZE = 500;
const SAMPLE_LIMIT = 5;

/** 表前缀与 service/config/database.php 的 'prefix' => 'game_' 保持一致 */
const TABLE = 'game_exchange_record';
const BAK_TABLE = 'game_exchange_record_bak';

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/**
 * 旧语义 out 行 → 新语义（bcmath scale 8）。
 *
 * @param array{platform_amount:string,game_amount:string,spread_fee:string} $row
 * @return array{game_amount:string,platform_amount:string}
 */
function convertedAmounts(array $row): array
{
    return [
        'game_amount'     => bcadd((string) $row['platform_amount'], '0', SCALE),
        'platform_amount' => bcsub((string) $row['game_amount'], (string) $row['spread_fee'], SCALE),
    ];
}

/** 不连库的换算自检，退出码非零即失败 */
function selfCheck(): void
{
    $cases = [
        // 线上真实形状：卖 950 游戏币，1 平台币 = 100 游戏币，点差 5%
        '卖 950 / 汇率 100 / 点差 5%' => [
            ['platform_amount' => '950.0000', 'game_amount' => '9.5000', 'spread_fee' => '0.4750'],
            ['game_amount' => '950.00000000', 'platform_amount' => '9.02500000'],
        ],
        // 点差为 0：净额 = 折算额
        '卖 50 / 汇率 1 / 点差 0' => [
            ['platform_amount' => '50.0000', 'game_amount' => '50.0000', 'spread_fee' => '0.0000'],
            ['game_amount' => '50.00000000', 'platform_amount' => '50.00000000'],
        ],
        // 小数金额 + 非整数汇率：差值仍精确到 4 位
        '卖 12.3456 / 汇率 7.5 / 点差 2.5%' => [
            ['platform_amount' => '12.3456', 'game_amount' => '1.6461', 'spread_fee' => '0.0412'],
            ['game_amount' => '12.34560000', 'platform_amount' => '1.60490000'],
        ],
    ];

    foreach ($cases as $name => [$old, $expected]) {
        $actual = convertedAmounts($old);
        foreach ($expected as $field => $value) {
            if ($actual[$field] !== $value) {
                fail("自检失败 [{$name}] {$field}: 期望 {$value}，实际 {$actual[$field]}");
            }
        }
        echo "ok  {$name}\n";
    }

    echo '自检通过：' . count($cases) . " 组换算\n";
}

function usage(): void
{
    $self = $argv[0] ?? __FILE__;
    fwrite(STDERR, <<<TXT
    历史 out 兑换记录语义迁移（默认 dry-run，不写库）

    用法：
      php {$self} --self-check                  # 不连库，只验换算
      php {$self} --before='YYYY-MM-DD HH:MM:SS' [--apply]

    参数：
      --before=…   修复上线时刻。只迁移 created_at 严格早于它的 out 行。无默认值。
      --apply      真正写库（先备份进 _bak，再更新）。缺 --before 时拒绝执行。

    连接信息取自环境变量：DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    （DB_DATABASE 必填，无默认值；本脚本不读 .env）

    TXT);
}

// ---------------------------------------------------------------- 参数

$before = '';
$apply = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--self-check') {
        selfCheck();
        exit(0);
    }
    if ($arg === '--apply') {
        $apply = true;
    } elseif (str_starts_with($arg, '--before=')) {
        $before = trim(substr($arg, strlen('--before=')));
    } elseif ($arg === '--help' || $arg === '-h') {
        usage();
        exit(0);
    } else {
        usage();
        fail("未知参数: {$arg}");
    }
}

// ---------------------------------------------------------------- 只读统计

// --apply 缺 --before 直接拒绝：连库之前就挡住，免得误以为「连上就能跑」
if ($apply && $before === '') {
    fail('--apply 必须显式给出 --before（修复上线时刻）：新旧行数值同形，不给时间窗无法判定范围');
}

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
        PDO::ATTR_STRINGIFY_FETCHES => true, // DECIMAL 必须按字符串取回，别落回 float
    ]);
} catch (Throwable $e) {
    fail('连接失败: ' . $e->getMessage());
}

echo '目标库: ' . $dbname . "\n";

if ($before === '') {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . TABLE . " WHERE direction = 'out'")->fetchColumn();
    echo "未指定 --before：无法判定「哪些 out 行是旧语义」，只统计规模\n";
    echo "  out 行总数: {$total}\n";
    echo "--apply 必须显式给出 --before（修复上线时刻）\n";
    exit(0);
}

$bakExists = (bool) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = ' . $pdo->quote(BAK_TABLE)
)->fetchColumn();

// 待迁移 = 早于截止时间的 out 行 − 已进备份表的行（幂等靠这一步）
$bakJoin   = $bakExists ? ' LEFT JOIN ' . BAK_TABLE . ' b ON b.id = r.id' : '';
$bakFilter = $bakExists ? ' AND b.id IS NULL' : '';
$scoped    = 'FROM ' . TABLE . ' r' . $bakJoin
    . " WHERE r.direction = 'out' AND r.created_at < ?" . $bakFilter;

$stmt = $pdo->prepare('SELECT COUNT(*) ' . $scoped);
$stmt->execute([$before]);
$pending = (int) $stmt->fetchColumn();

$migrated = 0;
if ($bakExists) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . TABLE . ' r JOIN ' . BAK_TABLE . ' b ON b.id = r.id'
        . " WHERE r.direction = 'out' AND r.created_at < ?"
    );
    $stmt->execute([$before]);
    $migrated = (int) $stmt->fetchColumn();
}

$stmt = $pdo->prepare(
    'SELECT r.id, r.user_id, r.created_at, r.platform_amount, r.game_amount, r.spread_fee '
    . $scoped . ' ORDER BY r.id LIMIT ' . SAMPLE_LIMIT
);
$stmt->execute([$before]);
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . TABLE . " WHERE direction = 'out' AND created_at >= ?");
$stmt->execute([$before]);
$after = (int) $stmt->fetchColumn();

echo '截止时间: ' . $before . "（只迁移 created_at 严格早于它的 out 行）\n";
echo '备份表 ' . BAK_TABLE . ': ' . ($bakExists ? '存在' : '不存在（--apply 时创建）') . "\n";
echo "待迁移: {$pending} 行\n";
if ($bakExists) {
    echo "已迁移（bak 中已有）: {$migrated} 行 —— 幂等跳过\n";
}
echo "晚于截止时间（按新语义写入，不动）: {$after} 行\n";

foreach ($samples as $row) {
    $new = convertedAmounts($row);
    echo "\n  id={$row['id']} created_at={$row['created_at']}\n";
    echo "    旧: platform_amount={$row['platform_amount']} game_amount={$row['game_amount']} spread_fee={$row['spread_fee']}\n";
    echo "    新: platform_amount={$new['platform_amount']} game_amount={$new['game_amount']} spread_fee={$row['spread_fee']}（不变）\n";
}

if (!$apply) {
    echo "\nDRY-RUN：未写任何数据。确认无误后加 --apply 再跑一次。\n";
    exit(0);
}

if ($pending === 0) {
    echo "\n没有需要迁移的行。\n";
    exit(0);
}

// ---------------------------------------------------------------- 写库

echo "\n即将写库：{$dbname}." . TABLE . " 共 {$pending} 行，原行先备份进 " . BAK_TABLE . "。\n";
echo '输入 MIGRATE 回车确认，其它任意输入中止: ';
if (trim((string) fgets(STDIN)) !== 'MIGRATE') {
    fail('已中止，未写任何数据');
}

if (!$bakExists) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . BAK_TABLE . ' LIKE ' . TABLE);
    $pdo->exec('ALTER TABLE ' . BAK_TABLE . ' ADD COLUMN migrated_at DATETIME NOT NULL');
    echo '已创建备份表 ' . BAK_TABLE . "\n";
}

$columns = 'id, user_id, game_id, currency_id, direction, platform_amount, game_amount, rate, spread_fee, created_at';
$update  = $pdo->prepare(
    'UPDATE ' . TABLE . ' SET game_amount = ?, platform_amount = ? WHERE id = ? AND direction = \'out\''
);

$done = 0;
while (true) {
    $pdo->beginTransaction();

    // 事务内锁行；bak 的 PK 保证同一行不会被备份两次
    $stmt = $pdo->prepare(
        'SELECT r.' . str_replace(', ', ', r.', $columns)
        . ' FROM ' . TABLE . ' r LEFT JOIN ' . BAK_TABLE . ' b ON b.id = r.id'
        . " WHERE r.direction = 'out' AND r.created_at < ? AND b.id IS NULL"
        . ' ORDER BY r.id LIMIT ' . BATCH_SIZE . ' FOR UPDATE'
    );
    $stmt->execute([$before]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        $pdo->commit();
        break;
    }

    $ids          = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // 备份原行（本语句先于下面的 UPDATE 执行，存的是旧值）
    $backup = $pdo->prepare(
        'INSERT INTO ' . BAK_TABLE . " ({$columns}, migrated_at)
         SELECT {$columns}, NOW() FROM " . TABLE . " WHERE id IN ({$placeholders})"
    );
    $backup->execute($ids);

    foreach ($rows as $row) {
        $new = convertedAmounts($row);
        $update->execute([$new['game_amount'], $new['platform_amount'], $row['id']]);
    }

    $pdo->commit();
    $done += count($rows);
    echo "  已迁移 {$done}/{$pending}\n";
}

echo "完成：{$done} 行已迁移，原值在 " . BAK_TABLE . "（PK=id，含 migrated_at）。\n";
