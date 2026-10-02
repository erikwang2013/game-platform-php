<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use app\model\AdminUser;
use app\model\OperationLog;
use app\model\AdminRole;
use common\model\PlatformConfig;
use support\Request;
use support\Response;

/**
 * 导出控制器的**非端点**部件（取数上限 / 截断信标 / 字段表 / 产物辅助），从 ExportController 逐行原样搬出。
 *
 * 为什么是 trait：这些方法本来就用 `$this->`（与控制器同生命周期），搬移只为把 ExportController
 * 压回 <500 行的仓库上限（admin/CLAUDE.md），**零行为变更**——方法名、可见性、默认值一字未改，
 * 单测里的反射调用（`callPrivate('fetchLimited' …)`）照旧命中，因为 trait 方法就是宿主类的方法。
 *
 * ⚠ 两个已核的边界：
 *  ① `declare(strict_types=1)` **不随 trait 传播**，各文件自己声明 —— 本文件已声明；
 *  ② 本文件只放**私有辅助方法**（可用 `grep -cE '^\s*#\[Apidoc'` 复核 = 0）；⚠ 别用不带 `^\s*` 锚点的
 *     版本——本注释自身就含这个字样，会把它一起数进去，那个读数数的是一句注释、不是属性。
 *     trait 方法在编译期就**摊平进宿主类**，apidoc 的反射目标是**用方类**（`$refClass->name`），
 *     故注解解析不受影响 —— 现成先例见同命名空间 `WithdrawReviewTrait`（它那 16 条 `#[Apidoc\`
 *     注解的两个端点照常进文档）。换句话说：**带注解的端点方法搬进 trait 也不会丢注解**。
 *
 * ⚠ `EXPORT_MAX_ROWS` 也搬进来了（读它的 `fetchLimited` / `markTruncated` 都在本文件）：
 *    留在控制器会变成「常量在这里、唯一读者在那里」的分脑状态。它在宿主类上仍是
 *    `ExportController::EXPORT_MAX_ROWS`，对外读法不变。
 */
trait ExportSupportTrait
{
    /**
     * 单次导出上限。⚠ **不许调大**：调大不是修好截断，只是把「静默少行」换成「静默 OOM」。
     * 触顶时由 markTruncated() 在文件里写可见提示 —— 导出是取数动作，「少了行且无从知道」最危险。
     */
    private const EXPORT_MAX_ROWS = 10000;

    /**
     * 下发临时导出产物，并在**本次连接关闭时**把它删掉；另有一层按时间的兜底进程
     * （app/process/ExportTmpCleanup，删超过 1 小时的 `export_*` / `receipt_*`）。
     *
     * 为什么不能在 `response()->download()` 之后直接 unlink：workerman 是**先把响应对象交回去、
     * 之后**才在 `Http::encode()` 里读这个文件（vendor/workerman/workerman/src/Protocols/Http.php:407-437：
     * <2MB 走 `file_get_contents` 一次性发，否则 `sendStream` 分片发）——提前删会把下载变成 0 字节。
     * 「连接关闭」是文件已经发完之后唯一稳定的信号（TcpConnection::destroy() 里 `($this->onClose)($this)`）。
     *
     * ⚠ 两个已知边界，都不影响正确性，只是文件在盘上多待一会儿：
     *  ① keep-alive 下连接可能很久才关（浏览器不关就一直不关）⇒ 靠兜底进程；
     *  ② CLI/单测里 `$request->connection` 是 null（请求构造自裸报文，见
     *     vendor/workerman/workerman/src/Protocols/Http/Request.php:60）⇒ 这时不注册钩子，
     *     文件只由兜底进程清。
     * 挂 onClose 用**链式**而不是覆盖：运维可能在 config 里给 worker 配过 onClose，覆盖会把它弄丢。
     */
    private function downloadTemp(string $tmpFile, string $filename, Request $request): Response
    {
        $connection = $request->connection;
        if ($connection !== null) {
            $previous = $connection->onClose;
            $connection->onClose = static function ($conn) use ($previous, $tmpFile): void {
                @unlink($tmpFile);
                if (is_callable($previous)) {
                    $previous($conn);
                }
            };
        }

        return response()->download($tmpFile, $filename);
    }

    /**
     * 构建 PDF HTML 模板
     */
    private function buildPdfHtml(string $type, string $title, array $data): string
    {
        $timestamp = date('Y-m-d H:i:s');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body { font-family: "DejaVu Sans", sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { font-size: 20px; color: #1677FF; margin-bottom: 4px; }
            .header .meta { font-size: 11px; color: #999; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th { background-color: #1677FF; color: #fff; padding: 8px 10px; text-align: left; font-size: 12px; }
            td { padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 11px; }
            tr:nth-child(even) { background-color: #fafafa; }
            .footer { text-align: center; font-size: 10px; color: #999; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
            .cards { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
            .card { flex: 1; min-width: 140px; padding: 16px; background: #f5f5f5; border-radius: 8px; text-align: center; }
            .card-label { font-size: 12px; color: #666; }
            .card-value { font-size: 24px; font-weight: bold; color: #1677FF; }
        </style></head><body>';

        $html .= '<div class="header">';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div class="meta">Copyright (c) 2026 erik &lt;erik@erik.xyz&gt; — https://erik.xyz</div>';
        $html .= '<div class="meta">' . trans('Exported at: %time%', ['%time%' => $timestamp]) . '</div>';
        $html .= '</div>';

        if ($type === 'dashboard') {
            $html .= '<div class="cards">';
            foreach ($data['stats'] ?? [] as $card) {
                $html .= '<div class="card"><div class="card-label">' . htmlspecialchars($card['label']) . '</div>';
                $html .= '<div class="card-value">' . htmlspecialchars($card['value']) . '</div></div>';
            }
            $html .= '</div>';
        } elseif (!empty($data['rows'])) {
            $html .= '<table><thead><tr>';
            foreach ($data['columns'] as $col) {
                $html .= '<th>' . htmlspecialchars($col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($data['rows'] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars((string) $cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer">Copyright (c) 2026 erik — https://erik.xyz | ' . trans('This file contains a non-removable copyright notice') . '</div>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * 列字母自增：A→B、Z→AA。
     *
     * 不用 `$col++`：PHP 8.5 起「非数字串自增」已弃用（PHP 9 移除）。chr(ord('Z')+1) 这类
     * 手写换算会在 Z 处得到 '['，所以走 PhpSpreadsheet 自带的列号↔字母换算（1 基，Z=26、AA=27）。
     */
    private static function nextColumn(string $col): string
    {
        return Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col) + 1);
    }

    /**
     * 取导出数据：**多取 1 行**当截断信标 —— 第 10001 行只用于判断「还有没有」，绝不进导出。
     *
     * @return array{0: array<int,mixed>, 1: bool} [行数据, 是否被截断]
     */
    private function fetchExportData(string $table, array $columns, array $conditions): array
    {
        $modelMap = [
            'admin_user' => AdminUser::class,
            'operation_log' => OperationLog::class,
            'admin_role' => AdminRole::class,
            // 请求参数里的 table 名保持 'system_config'（前端契约不动），读的是 platform_config：
            // 与 ConfigController 同一张真值表，否则导出会是一张永远空/永远陈旧的表。
            'system_config' => PlatformConfig::class,
        ];

        if (!isset($modelMap[$table])) {
            return [[], false];
        }

        $model = new $modelMap[$table]();
        $query = $model->newQuery();

        foreach ($conditions as $field => $value) {
            if (!empty($value) || $value === '0') {
                $query->where($field, $value);
            }
        }

        [$rows, $truncated] = $this->fetchLimited($query);
        return [$rows->toArray(), $truncated];
    }

    /**
     * 多取 1 行当截断信标：只有真存在第 10001 行才算触顶（恰好 10000 行不算），
     * 多出来那行返回前切掉，不落进任何产物。
     *
     * @return array{0: Collection, 1: bool} [行集合, 是否被截断]
     */
    private function fetchLimited(Builder $query): array
    {
        $rows = $query->limit(self::EXPORT_MAX_ROWS + 1)->get();
        if ($rows->count() <= self::EXPORT_MAX_ROWS) {
            return [$rows, false];
        }
        return [$rows->take(self::EXPORT_MAX_ROWS), true];
    }

    /**
     * 把截断提示写进产物本身（数据区下一行的 A 列，加粗红字）。
     *
     * 只写响应头没用：浏览器下载一条流，人只看得到文件内容。
     */
    private function markTruncated(Worksheet $sheet, int $row): void
    {
        $sheet->getCell('A' . $row)->setValue(trans(
            'Export truncated: only the first %max% matching rows are included. Narrow the filters and export again for the rest.',
            ['%max%' => (string) self::EXPORT_MAX_ROWS]
        ));
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'C00000']],
        ]);
    }

    private function getExportColumns(string $table): array
    {
        $maps = [
            'admin_user' => [
                'id' => trans('User ID'), 'username' => trans('Username'), 'real_name' => trans('Real name'),
                'phone' => trans('Phone'), 'email' => trans('Email'), 'status' => trans('Status'),
                'last_login_at' => trans('Last login time'), 'last_login_ip' => trans('Last login IP'),
                'created_at' => trans('Created at'),
            ],
            'operation_log' => [
                'id' => 'ID', 'user_id' => trans('User ID'), 'action' => trans('Action'),
                'method' => trans('Request method'), 'path' => trans('Request path'), 'ip' => trans('IP address'),
                'created_at' => trans('Operated at'),
            ],
            'admin_role' => [
                'id' => 'ID', 'name' => trans('Role name'), 'slug' => trans('Role slug'),
                'description' => trans('Description'), 'status' => trans('Status'), 'created_at' => trans('Created at'),
            ],
            'system_config' => [
                'id' => 'ID', 'group' => trans('Group'), 'key' => trans('Config key'),
                'value' => trans('Config value'), 'type' => trans('Type'), 'description' => trans('Notes'),
                'created_at' => trans('Created at'),
            ],
        ];

        return $maps[$table] ?? [];
    }

    private function getSensitiveFields(string $table): array
    {
        $maps = [
            'admin_user' => ['phone', 'email', 'id_card'],
        ];
        return $maps[$table] ?? [];
    }
}
