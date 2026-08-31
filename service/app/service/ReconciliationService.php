<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use common\SnowflakeService;
use common\model\DepositOrder;
use common\model\PaymentMethod;
use app\model\ReconciliationBatch;
use app\model\ReconciliationDiff;
use app\model\ReconciliationStatement;
use common\model\Transaction;
use app\payment\StatementSourceResolver;
use support\Db;
use support\Log;

/**
 * 对账编排：建批次 → 取明细 → 标准化 → 匹配 → 落差异。
 *
 * 只落差异，不落匹配成功（健康系统下匹配成功占 99%+，statement 表的
 * matched / local_order_id 字段已足够回溯匹配结果）。
 *
 * 匹配键优先级：transaction_id → order_no。日期只用于圈定扫描范围，
 * 不参与匹配判定——扫描窗口向两侧各扩 12h，规避网关按 UTC 结算日切日
 * 与本地按 paid_at 切日导致的假差异。
 */
class ReconciliationService
{
    /** 金额容差：差额低于此值不记差异（汇率舍入噪音） */
    private const AMOUNT_TOLERANCE = '0.01';

    /** 单批次明细行数上限（防异常大文件打爆内存） */
    private const MAX_ROWS = 200000;

    /** 差异类型 */
    public const TYPE_AMOUNT_MISMATCH = 'amount_mismatch';
    public const TYPE_STATUS_MISMATCH = 'status_mismatch';
    public const TYPE_MISSING_LOCAL = 'missing_local';
    public const TYPE_MISSING_GATEWAY = 'missing_gateway';
    public const TYPE_DUPLICATE_DEPOSIT = 'duplicate_deposit';
    public const TYPE_PAYOUT_UNCONFIRMED = 'payout_unconfirmed';
    public const TYPE_TIME_ONLY = 'time_only';
    public const TYPE_CURRENCY_MISMATCH = 'currency_mismatch';

    /** 处理状态白名单 */
    private const RESOLUTIONS = ['pending', 'resolved', 'ignored'];

    /** 网关侧视为"已结算"的状态（小写比较） */
    private const GATEWAY_SUCCESS = ['settled', 'paid', 'success', 'succeeded', 'confirmed', 'credited', 'captured', 'completed'];

    /** 本地视为"已到账"的充值状态 */
    private const LOCAL_PAID = ['paid', 'confirmed'];

    /** 网关原生状态 → 归一化状态 */
    private const STATUS_MAP = [
        'credited' => 'settled', 'paid' => 'settled', 'success' => 'settled',
        'succeeded' => 'settled', 'captured' => 'settled', 'completed' => 'settled',
        'confirmed' => 'settled', 'pending' => 'pending', 'refunded' => 'refunded', 'failed' => 'failed',
    ];

    /**
     * CSV 列名映射：provider => [标准字段 => 网关CSV列名]。
     * 未配置的网关用泛用映射（列名 = 标准字段名，大小写不敏感）。
     *
     * ponytail: 映射写在代码里，新接网关 = 加一段常量；网关多到影响可读性时移到 config/statement_csv.php。
     */
    private const CSV_COLUMNS = [
        'skrill' => [
            'external_id' => 'Transaction ID', 'amount' => 'Amount', 'currency' => 'Currency',
            'transaction_time' => 'Date', 'order_no' => 'Reference ID', 'status' => 'Status',
        ],
        'neteller' => [
            'external_id' => 'Transaction ID', 'amount' => 'Transaction Amount', 'currency' => 'Currency',
            'transaction_time' => 'Date', 'order_no' => 'Customer Reference', 'status' => 'Status',
        ],
    ];

    /**
     * 创建对账批次。
     *
     * @return int 批次 ID；参数非法返回 0
     */
    public static function createBatch(string $name, string $gateway, string $startDate, string $endDate): int
    {
        $name = trim($name);
        $gateway = trim($gateway);
        if ($name === '' || $gateway === '' || !self::isDate($startDate) || !self::isDate($endDate) || $startDate > $endDate) {
            return 0;
        }

        $batch = new ReconciliationBatch();
        $batch->id = SnowflakeService::generate();
        $batch->name = mb_substr($name, 0, 128);
        $batch->gateway = mb_substr($gateway, 0, 64);
        $batch->date_range_start = $startDate;
        $batch->date_range_end = $endDate;
        $batch->status = 'pending';
        $batch->save();

        return (int) $batch->id;
    }

    /**
     * 执行批次对账（自动拉取通道）。
     *
     * 网关不支持自动拉取时批次标 failed，调用方应改用 importCsv()。
     *
     * @return array{ok: bool, error?: string, message?: string, batch_id: int, total_statements?: int, matched_count?: int, diff_count?: int}
     */
    public static function runBatch(int $batchId): array
    {
        $batch = ReconciliationBatch::find($batchId);
        if ($batch === null) {
            return ['ok' => false, 'error' => 'batch_not_found', 'batch_id' => $batchId];
        }
        if ((string) $batch->status === 'running') {
            return ['ok' => false, 'error' => 'batch_already_running', 'batch_id' => (int) $batch->id];
        }
        // 已完成批次直接返回：重跑会清旧差异（含已处理），幂等守卫
        if ((string) $batch->status === 'completed') {
            return ['ok' => true, 'already_done' => true, 'batch_id' => (int) $batch->id];
        }

        $batch->status = 'running';
        $batch->save();

        try {
            $gateway = (string) $batch->gateway;
            $source = StatementSourceResolver::resolve($gateway);
            if ($source === null) {
                self::markFailed($batch, '该网关不支持自动拉取对账单，请使用 CSV 上传通道');
                return ['ok' => false, 'error' => 'gateway_requires_csv', 'message' => '该网关不支持自动拉取对账单，请使用 CSV 上传通道', 'batch_id' => (int) $batch->id];
            }

            $statements = self::normalize($source->fetchStatement(
                $gateway,
                (string) $batch->date_range_start,
                (string) $batch->date_range_end
            ), $gateway);

            return self::reconcile($batch, $statements);
        } catch (\Throwable $e) {
            self::markFailed($batch, $e->getMessage());
            return ['ok' => false, 'error' => 'exception', 'message' => $e->getMessage(), 'batch_id' => (int) $batch->id];
        }
    }

    /**
     * 执行批次对账（人工 CSV 通道）。
     *
     * @return array{ok: bool, error?: string, message?: string, batch_id: int, total_statements?: int, matched_count?: int, diff_count?: int}
     */
    public static function importCsv(int $batchId, string $csvPath): array
    {
        $batch = ReconciliationBatch::find($batchId);
        if ($batch === null) {
            return ['ok' => false, 'error' => 'batch_not_found', 'batch_id' => $batchId];
        }
        if ((string) $batch->status === 'running') {
            return ['ok' => false, 'error' => 'batch_already_running', 'batch_id' => (int) $batch->id];
        }
        // 已完成批次直接返回：重跑会清旧差异（含已处理），幂等守卫
        if ((string) $batch->status === 'completed') {
            return ['ok' => true, 'already_done' => true, 'batch_id' => (int) $batch->id];
        }

        $batch->status = 'running';
        $batch->save();

        try {
            $statements = self::parseCsv($csvPath, (string) $batch->gateway);
            if ($statements === null) {
                self::markFailed($batch, 'CSV 无法解析或缺少必要列');
                return ['ok' => false, 'error' => 'csv_unparseable', 'message' => 'CSV 无法解析或缺少必要列', 'batch_id' => (int) $batch->id];
            }
            return self::reconcile($batch, $statements);
        } catch (\Throwable $e) {
            self::markFailed($batch, $e->getMessage());
            return ['ok' => false, 'error' => 'exception', 'message' => $e->getMessage(), 'batch_id' => (int) $batch->id];
        }
    }

    /**
     * 对账主流程：匹配（内存哈希）→ 落明细 → 落差异 → 汇总。
     *
     * 先匹配再落库，两张表各一次批量插入。重跑同一批次时，清旧数据与重建
     * 结果包在同一个事务里——中途失败不丢旧差异（含已处理），批次保持 running 可重试。
     */
    private static function reconcile(ReconciliationBatch $batch, array $statements): array
    {
        if (count($statements) > self::MAX_ROWS) {
            throw new \RuntimeException("明细行数超过上限 " . self::MAX_ROWS);
        }

        // 取本地订单（含未到账，状态判定留到匹配后）
        $from = date('Y-m-d H:i:s', strtotime((string) $batch->date_range_start . ' -12 hours'));
        $to = date('Y-m-d H:i:s', strtotime((string) $batch->date_range_end . ' +12 hours'));
        $local = self::loadLocalOrders((string) $batch->gateway, $from, $to);

        $byTx = [];
        $byOrderNo = [];
        foreach ($local as $order) {
            if ($order->transaction_id !== '') {
                $byTx[mb_strtolower($order->transaction_id)] = $order;
            }
            $byOrderNo[$order->order_no] = $order;
        }

        $matchedIds = [];
        $diffs = [];
        $matched = 0;
        $matches = []; // statement 下标 => 匹配到的本地订单（null=未匹配）
        $now = date('Y-m-d H:i:s');

        foreach ($statements as $i => $s) {
            $key = mb_strtolower($s['external_id']);
            $order = ($key !== '' && isset($byTx[$key])) ? $byTx[$key] : null;
            if ($order === null && $s['order_no'] !== '') {
                $order = $byOrderNo[$s['order_no']] ?? null;
            }
            $matches[$i] = $order;

            if ($order === null) {
                $diffs[] = self::makeDiff($batch, self::TYPE_MISSING_LOCAL, 'critical', null, 0, $now,
                    "网关有明细但本地无匹配订单：external_id={$s['external_id']} 金额 {$s['amount']}{$s['currency']}，可执行补单");
                continue;
            }

            $matched++;
            $matchedIds[(string) $order->id] = true;

            $diffCountBefore = count($diffs);

            $gap = self::amountGap($s['amount'], (string) $order->amount);
            if (bccomp($gap, self::AMOUNT_TOLERANCE, 4) > 0) {
                $sign = bcsub($s['amount'], (string) $order->amount, 4) > 0 ? '+' : '-';
                $diffs[] = self::makeDiff($batch, self::TYPE_AMOUNT_MISMATCH, 'high', (int) $order->id, 0, $now,
                    "金额不一致：网关 {$s['amount']}{$s['currency']} / 本地 {$order->amount}{$order->currency}（差 {$sign}{$gap}）");
            }

            $gwOk = in_array($s['status'], self::GATEWAY_SUCCESS, true);
            $localOk = in_array((string) $order->status, self::LOCAL_PAID, true);
            if ($gwOk && !$localOk) {
                $diffs[] = self::makeDiff($batch, self::TYPE_STATUS_MISMATCH, 'critical', (int) $order->id, 0, $now,
                    "状态不一致：网关 {$s['status']} 但本地订单 {$order->order_no} 仍为 {$order->status}，需补登流水/补发游戏币");
            } elseif (!$gwOk && $localOk) {
                $diffs[] = self::makeDiff($batch, self::TYPE_STATUS_MISMATCH, 'high', (int) $order->id, 0, $now,
                    "状态不一致：本地订单 {$order->order_no} 已 {$order->status} 但网关为 {$s['status']}");
            }

            // 币种不一致（medium）：网关币种与本地订单币种不符
            if ($s['currency'] !== '' && mb_strtoupper($s['currency']) !== mb_strtoupper((string) $order->currency)) {
                $diffs[] = self::makeDiff($batch, self::TYPE_CURRENCY_MISMATCH, 'medium', (int) $order->id, 0, $now,
                    "币种不一致：网关 {$s['currency']} / 本地 {$order->currency}");
            }

            // 仅时间不一致（low）：金额/状态均一致时才记，避免与其它差异叠加成噪音
            if (count($diffs) === $diffCountBefore
                && $s['transaction_time'] !== null
                && $order->paid_at !== null
                && abs(strtotime($s['transaction_time']) - strtotime((string) $order->paid_at)) > 86400
            ) {
                $diffs[] = self::makeDiff($batch, self::TYPE_TIME_ONLY, 'low', (int) $order->id, 0, $now,
                    "仅时间不一致：网关 {$s['transaction_time']} / 本地支付 {$order->paid_at}");
            }
        }

        // 落对账单明细（含匹配结果）
        $rows = [];
        foreach ($statements as $i => $s) {
            $order = $matches[$i] ?? null;
            $rows[] = [
                'id' => SnowflakeService::generate(),
                'batch_id' => $batch->id,
                'gateway' => (string) $batch->gateway,
                'external_id' => $s['external_id'],
                'amount' => $s['amount'],
                'currency' => $s['currency'],
                'status' => $s['status'],
                'transaction_time' => $s['transaction_time'],
                'local_order_id' => $order !== null ? (int) $order->id : null,
                'matched' => $order !== null ? 1 : 0,
                'raw' => json_encode($s['raw'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ];
        }
        // 本地有、网关无（仅已到账订单判定——未到账订单本就不该出现在对账单上）
        foreach ($local as $order) {
            if (!isset($matchedIds[(string) $order->id])
                && in_array((string) $order->status, self::LOCAL_PAID, true)
            ) {
                $diffs[] = self::makeDiff($batch, self::TYPE_MISSING_GATEWAY, 'critical', (int) $order->id, 0, $now,
                    "本地订单 {$order->order_no}（{$order->amount}{$order->currency}，{$order->status}）在对账单中无对应明细，资金可能未到账");
            }
        }

        // 重复入账：同一订单的 deposit 流水 > 1 笔
        foreach (self::duplicateDeposits(array_keys($matchedIds), $from, $to) as $orderId => $count) {
            $diffs[] = self::makeDiff($batch, self::TYPE_DUPLICATE_DEPOSIT, 'critical', (int) $orderId, 0, $now,
                "订单 {$orderId} 有 {$count} 笔 deposit 流水，疑似重复入账，需冲正多余流水并扣回余额");
        }

        // 清旧建新同一事务：重跑不丢已处理差异，中途失败批次保持 running 可重试
        $ok = Db::transaction(function () use ($batch, $rows, $diffs, $matched, $now) {
            ReconciliationStatement::where('batch_id', $batch->id)->delete();
            ReconciliationDiff::where('batch_id', $batch->id)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                ReconciliationStatement::insert($chunk);
            }
            if ($diffs !== []) {
                foreach (array_chunk($diffs, 500) as $chunk) {
                    ReconciliationDiff::insert($chunk);
                }
            }

            $batch->status = 'completed';
            $batch->total_statements = count($rows);
            $batch->matched_count = $matched;
            $batch->diff_count = count($diffs);
            $batch->save();

            return true;
        });
        if ($ok !== true) {
            throw new \RuntimeException('对账结果落库失败');
        }

        return [
            'ok' => true,
            'batch_id' => (int) $batch->id,
            'total_statements' => count($rows),
            'matched_count' => $matched,
            'diff_count' => count($diffs),
        ];
    }

    /**
     * 查询批次对账单明细。
     *
     * @return array{items: array, total: int, page: int, limit: int}
     */
    public static function getStatements(int $batchId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $query = ReconciliationStatement::where('batch_id', $batchId);
        return [
            'items' => $query->orderBy('id')->forPage($page, $limit)->get()->toArray(),
            'total' => (int) $query->count(),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 查询批次差异列表。critical 优先、未处理优先、时间倒序。
     *
     * @return array{items: array, total: int, page: int, limit: int}
     */
    public static function getDiffs(int $batchId, ?string $diffType = null, ?string $resolution = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $query = ReconciliationDiff::where('batch_id', $batchId);
        if ($diffType !== null && $diffType !== '') {
            $query->where('diff_type', $diffType);
        }
        if ($resolution !== null && $resolution !== '') {
            $query->where('resolution', $resolution);
        }
        $items = self::orderDiffs($query)->forPage($page, $limit)->get()->toArray();
        return [
            'items' => $items,
            'total' => (int) $query->count(),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 跨批次差异列表（导出口用，上限 5000 行）。
     *
     * @return array<array<string, mixed>>
     */
    public static function listDiffs(?string $resolution = null, ?string $diffType = null, int $limit = 5000): array
    {
        $query = ReconciliationDiff::query();
        if ($resolution !== null && $resolution !== '') {
            $query->where('resolution', $resolution);
        }
        if ($diffType !== null && $diffType !== '') {
            $query->where('diff_type', $diffType);
        }
        return self::orderDiffs($query)
            ->limit(min(5000, max(1, $limit)))
            ->get()
            ->toArray();
    }

    /**
     * 处理差异。
     *
     * @param int    $diffId     差异 ID
     * @param string $resolution pending / resolved / ignored
     * @param int    $userId     处理人（管理员 ID）
     */
    public static function resolveDiff(int $diffId, string $resolution, int $userId): bool
    {
        if (!in_array($resolution, self::RESOLUTIONS, true)) {
            return false;
        }
        $diff = ReconciliationDiff::find($diffId);
        if ($diff === null) {
            return false;
        }
        if ((string) $diff->resolution === $resolution) {
            return true;
        }
        $diff->resolution = $resolution;
        $diff->resolved_by = $resolution === 'pending' ? null : $userId;
        $diff->resolved_at = $resolution === 'pending' ? null : date('Y-m-d H:i:s');
        return (bool) $diff->save();
    }

    // ============================================================
    // 内部
    // ============================================================

    /**
     * 取本地充值订单（含未到账——网关已 settled 但本地未到账的 STATUS_MISMATCH 靠它暴露）。
     * provider 经 payment_method_id → game_payment_method 关联取（不改 game_deposit_order 表结构）。
     */
    private static function loadLocalOrders(string $gateway, string $from, string $to): array
    {
        $methodIds = PaymentMethod::where('provider', $gateway)->pluck('id')->toArray();
        if ($methodIds === []) {
            return [];
        }
        return DepositOrder::whereBetween('paid_at', [$from, $to])
            ->whereIn('payment_method_id', $methodIds)
            ->get(['id', 'order_no', 'amount', 'currency', 'status', 'transaction_id', 'paid_at', 'user_id'])
            ->all();
    }

    /**
     * 检测重复入账：同 ref_id 的 deposit 流水 > 1 笔。
     *
     * @return array<string, int> ref_id => 流水笔数
     */
    private static function duplicateDeposits(array $orderIds, string $from, string $to): array
    {
        if ($orderIds === []) {
            return [];
        }
        return Transaction::where('ref_type', 'deposit')
            ->whereIn('ref_id', $orderIds)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('ref_id')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('ref_id, COUNT(*) as cnt')
            ->pluck('cnt', 'ref_id')
            ->toArray();
    }

    /**
     * 网关原生行 → 统一结构。未知字段原样保留在 raw 供追溯。
     *
     * @return array<int, array{external_id: string, amount: string, currency: string, status: string, transaction_time: ?string, order_no: string, raw: array<string, mixed>}>
     */
    private static function normalize(array $rawRows, string $gateway): array
    {
        $out = [];
        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $orderNo = self::str($row['order_no'] ?? $row['merchant_order_no'] ?? '');
            $external = self::str($row['external_id'] ?? $row['transaction_id'] ?? $row['ref_id'] ?? '');
            if ($external === '' && $orderNo === '') {
                continue; // 无匹配键的行无法对账
            }

            $status = mb_strtolower(self::str($row['status'] ?? $row['state'] ?? 'settled'));
            $time = self::str($row['transaction_time'] ?? $row['settled_at'] ?? $row['date'] ?? $row['created_at'] ?? '');

            $out[] = [
                'external_id' => mb_substr($external, 0, 128),
                'amount' => self::money($row['amount'] ?? $row['gross_amount'] ?? 0),
                'currency' => mb_substr(self::str($row['currency'] ?? ''), 0, 16),
                'status' => self::STATUS_MAP[$status] ?? $status,
                'transaction_time' => $time !== '' && (strtotime($time) ?: 0) > 0 ? date('Y-m-d H:i:s', strtotime($time)) : null,
                'order_no' => $orderNo,
                'raw' => $row,
            ];
        }
        return $out;
    }

    /**
     * 解析人工上传的 CSV。
     *
     * @return array|null null=无法解析或缺少必要列
     */
    private static function parseCsv(string $path, string $gateway): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $delim = self::detectDelimiter($path);
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        $header = self::readCsvLine($handle, $delim);
        if ($header === null) {
            fclose($handle);
            return null;
        }

        // [列名小写 => 列索引]
        $index = [];
        foreach ($header as $i => $col) {
            $index[mb_strtolower((string) $col)] = $i;
        }

        $map = self::CSV_COLUMNS[$gateway] ?? [];
        $cols = [];
        foreach (['external_id', 'amount', 'currency', 'status', 'transaction_time', 'order_no'] as $field) {
            $cols[$field] = $index[mb_strtolower($map[$field] ?? $field)] ?? null;
        }

        if ($cols['external_id'] === null) {
            fclose($handle);
            return null; // 无交易 ID 列，无法对账
        }

        $rows = [];
        while (($line = self::readCsvLine($handle, $delim)) !== null) {
            if ($line === []) {
                continue;
            }
            $rows[] = [
                'external_id' => $line[$cols['external_id']] ?? '',
                'amount' => $line[$cols['amount']] ?? '',
                'currency' => $line[$cols['currency']] ?? '',
                'status' => $line[$cols['status']] ?? '',
                'transaction_time' => $line[$cols['transaction_time']] ?? '',
                'order_no' => $line[$cols['order_no']] ?? '',
            ];
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }
        fclose($handle);

        if ($rows === []) {
            return null;
        }
        return self::normalize($rows, $gateway);
    }

    /**
     * 读一行 CSV，列名=>值。自动处理 UTF-8 BOM 与 GBK 编码。
     *
     * @return array<string, mixed>|null
     */
    private static function readCsvLine($handle, string $delim): ?array
    {
        $raw = fgetcsv($handle, 0, $delim);
        if ($raw === false) {
            return null;
        }
        $raw = array_map(fn($v) => is_string($v) ? trim($v) : $v, $raw);
        // GBK / GB2312 编码自动转 UTF-8（部分网关门户导出是本地编码）
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', ['GBK', 'CP936', 'GB2312']);
            if ($converted !== false) {
                $raw = $converted;
            }
        }
        return $raw === [] ? null : $raw;
    }

    /**
     * 探测 CSV 分隔符（逗号/分号/制表符，取首行出现次数最多者）。
     */
    private static function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }
        $first = (string) (stream_get_contents($handle, 8192) ?? '');
        fclose($handle);
        if (substr($first, 0, 3) === "\xEF\xBB\xBF") {
            $first = substr($first, 3);
        }

        $counts = [
            ',' => substr_count($first, ','),
            ';' => substr_count($first, ';'),
            "\t" => substr_count($first, "\t"),
        ];
        $winner = ',';
        foreach ($counts as $d => $c) {
            if ($c > $counts[$winner]) {
                $winner = $d;
            }
        }
        return $counts[$winner] === 0 ? ',' : $winner;
    }

    /**
     * 标记批次失败。原因写入批次 error_msg 字段 + 日志，供管理端排查。
     */
    private static function markFailed(ReconciliationBatch $batch, string $reason): void
    {
        $batch->status = 'failed';
        $batch->error_msg = mb_substr($reason, 0, 512);
        $batch->save();
        try {
            Log::channel('default')->error("reconciliation batch {$batch->id} failed: {$reason}");
        } catch (\Throwable) {
            // 日志不可用不应阻断失败落库
        }
    }

    /**
     * 差异排序：critical 优先、未处理优先、ID 倒序。
     */
    private static function orderDiffs($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->orderByRaw("CASE `severity` WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE `resolution` WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc');
    }

    private static function makeDiff(
        ReconciliationBatch $batch,
        string $type,
        string $severity,
        ?int $localOrderId,
        int $statementId,
        string $createdAt,
        string $description
    ): array {
        return [
            'id' => SnowflakeService::generate(),
            'batch_id' => $batch->id,
            'statement_id' => $statementId,
            'local_order_id' => $localOrderId,
            'diff_type' => $type,
            'severity' => $severity,
            'description' => mb_substr($description, 0, 512),
            'resolution' => 'pending',
            'resolved_by' => null,
            'resolved_at' => null,
            'created_at' => $createdAt,
        ];
    }

    /**
     * |网关金额 - 本地金额|
     */
    private static function amountGap(string $gatewayAmount, string $localAmount): string
    {
        return bcabs(bcsub($gatewayAmount, $localAmount, 4));
    }

    private static function isDate(string $d): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return false;
        }
        $t = strtotime($d);
        return $t !== false && date('Y-m-d', $t) === $d;
    }

    private static function str(mixed $v): string
    {
        if (is_array($v)) {
            return '';
        }
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function money(mixed $v): string
    {
        $s = self::str($v);
        if ($s === '') {
            return '0.0000';
        }
        // 剥离千分位与货币符号
        $s = (string) (preg_replace('/[^\d.\-]/', '', $s) ?? '');
        if ($s === '' || $s === '-' || !is_numeric($s)) {
            return '0.0000';
        }
        return bcadd($s, '0', 4);
    }
}
