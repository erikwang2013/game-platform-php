<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use common\EncryptionService;
use app\model\AdminUser;
use app\model\OperationLog;
use app\model\AdminRole;
use common\model\PlatformConfig;
use common\model\User;
use common\model\DepositOrder;
use common\model\WithdrawOrder;
use common\model\Transaction;
use support\Request;
use support\Response;

#[Apidoc\Title("数据导出")]
#[Apidoc\Group("export")]
class ExportController extends BaseController
{
    #[Apidoc\Title("Excel导出")]
    #[Apidoc\Desc("将指定数据表导出为Excel文件")]
    #[Apidoc\Url("/admin/v1/export/excel")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "table", type: "string", require: true, desc: "数据表名(admin_user,operation_log,admin_role,system_config)")]
    #[Apidoc\Param(name: "columns", type: "array", require: false, desc: "导出列名数组")]
    #[Apidoc\Param(name: "conditions", type: "object", require: false, desc: "查询条件")]
    #[Apidoc\Param(name: "title", type: "string", require: false, desc: "导出文件标题")]
    public function excel(Request $request): Response
    {
        $table = $request->input('table', 'admin_user');
        $columns = $request->input('columns', []);
        $conditions = $request->input('conditions', []);
        $title = $request->input('title', trans('Data export'));

        // 获取导出字段映射
        $exportColumns = $this->getExportColumns($table);
        if (empty($columns)) {
            $columns = array_keys($exportColumns);
        }

        // 查询数据
        $data = $this->fetchExportData($table, $columns, $conditions);
        $sensitiveFields = $this->getSensitiveFields($table);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);

        // 表头样式
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1677FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // 数据行样式
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $colIndex = 'A';
        foreach ($columns as $col) {
            $label = $exportColumns[$col] ?? $col;
            $cell = $sheet->getCell($colIndex . '1');
            $cell->setValue($label);
            $sheet->getStyle($colIndex . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($colIndex)->setAutoSize(true);
            $colIndex++;
        }

        // 填充数据
        $row = 2;
        foreach ($data as $item) {
            $colIndex = 'A';
            foreach ($columns as $col) {
                $value = $item[$col] ?? '';
                if (in_array($col, $sensitiveFields) && !empty($value)) {
                    // ⚠ 这里**不能**再 decrypt：$item 来自 AdminUser::get()->toArray()，而
                    // phone/email/id_card 是 Encryptable cast（app/model/AdminUser.php:37-39）——
                    // Eloquent 取值那一刻已解过密，toArray() 拿到的**就是明文**。
                    // 对明文再解一次 ⇒ EncryptionException: Invalid ciphertext prefix for AES-256-CBC。
                    // 空值走 EncryptionService::decrypt 的 early-return，所以**只在有值时才炸** ——
                    // 表里只要有任意一个管理员填过手机号/邮箱，本端点（table 默认 admin_user、
                    // columns 默认取全部、phone/email 在 sensitiveFields 里）就必 500。
                    // 脱敏才是这里唯一该做的事。
                    if ($col === 'phone') {
                        $value = EncryptionService::maskPhone((string) $value);
                    } elseif ($col === 'email') {
                        $value = EncryptionService::maskEmail((string) $value);
                    } else {
                        $value = str_repeat('*', 8); // id_card等彻底隐藏
                    }
                }
                $sheet->getCell($colIndex . $row)->setValue($value);
                $sheet->getStyle($colIndex . $row)->applyFromArray($dataStyle);
                $colIndex++;
            }
            $row++;
        }

        // 冻结首行
        $sheet->freezePane('A2');
        // 自动筛选
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        $filename = sprintf('export_%s_%s.xlsx', $table, date('YmdHis'));
        $tmpFile = runtime_path() . '/tmp/' . $filename;

        $dir = dirname($tmpFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return $this->downloadTemp($tmpFile, $filename, $request);
    }

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

    #[Apidoc\Title("PDF导出")]
    #[Apidoc\Desc("将数据导出为PDF文件")]
    #[Apidoc\Url("/admin/v1/export/pdf")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "导出类型(table,dashboard)")]
    #[Apidoc\Param(name: "title", type: "string", require: false, desc: "PDF标题")]
    #[Apidoc\Param(name: "data", type: "object", require: false, desc: "导出数据")]
    public function pdf(Request $request): Response
    {
        $type = $request->input('type', 'table');
        $title = $request->input('title', trans('Data export'));
        $data = $request->input('data', []);

        $html = $this->buildPdfHtml($type, $title, $data);

        $dompdf = new Dompdf();
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $filename = sprintf('export_%s_%s.pdf', $type, date('YmdHis'));
        $tmpFile = runtime_path() . '/tmp/' . $filename;

        $dir = dirname($tmpFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($tmpFile, $dompdf->output());

        return $this->downloadTemp($tmpFile, $filename, $request);
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

    #[Apidoc\Title("导出用户Excel")]
    #[Apidoc\Desc("导出C端平台用户数据到Excel文件")]
    #[Apidoc\Url("/admin/v1/export/users")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "用户状态(0禁用,1启用)")]
    public function exportUsers(Request $request): \Webman\Http\Response
    {
        $query = User::orderBy('id', 'desc');
        if ($request->has('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        $users = $query->limit(10000)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(trans('User list'));

        $headers = ['ID', trans('Username'), trans('Nickname'), trans('Country'), trans('Status'), trans('Last login'), trans('Registered at')];
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1677FF']],
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->getCell($col . '1')->setValue($h);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $col++;
        }

        $row = 2;
        foreach ($users as $u) {
            $sheet->getCell('A' . $row)->setValue($this->encodeId($u->id));
            $sheet->getCell('B' . $row)->setValue($u->username);
            $sheet->getCell('C' . $row)->setValue($u->nickname);
            $sheet->getCell('D' . $row)->setValue($u->country);
            $sheet->getCell('E' . $row)->setValue($u->status == 1 ? trans('Enabled') : trans('Disabled'));
            $sheet->getCell('F' . $row)->setValue($u->last_login_at);
            $sheet->getCell('G' . $row)->setValue($u->created_at);
            $row++;
        }

        $filename = 'export_users_' . date('YmdHis') . '.xlsx';
        $tmpFile = runtime_path() . '/tmp/' . $filename;
        $dir = dirname($tmpFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return $this->downloadTemp($tmpFile, $filename, $request);
    }

    #[Apidoc\Title("导出流水Excel")]
    #[Apidoc\Desc("导出平台交易流水数据到Excel文件")]
    #[Apidoc\Url("/admin/v1/export/transactions")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "type", type: "string", require: false, desc: "流水类型")]
    public function exportTransactions(Request $request): \Webman\Http\Response
    {
        $query = Transaction::orderBy('created_at', 'desc');
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $transactions = $query->limit(10000)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(trans('Platform transactions'));

        $headers = ['ID', trans('User ID'), trans('Type'), trans('Amount'), trans('Balance'), trans('Related type'), trans('Remark'), trans('Time')];
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1677FF']],
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->getCell($col . '1')->setValue($h);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $col++;
        }

        $row = 2;
        foreach ($transactions as $t) {
            $sheet->getCell('A' . $row)->setValue($this->encodeId($t->id));
            $sheet->getCell('B' . $row)->setValue($this->encodeId($t->user_id));
            $sheet->getCell('C' . $row)->setValue($t->type);
            $sheet->getCell('D' . $row)->setValue($t->amount);
            $sheet->getCell('E' . $row)->setValue($t->balance_after);
            $sheet->getCell('F' . $row)->setValue($t->ref_type);
            $sheet->getCell('G' . $row)->setValue($t->remark);
            $sheet->getCell('H' . $row)->setValue($t->created_at);
            $row++;
        }

        $filename = 'export_transactions_' . date('YmdHis') . '.xlsx';
        $tmpFile = runtime_path() . '/tmp/' . $filename;
        $dir = dirname($tmpFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return $this->downloadTemp($tmpFile, $filename, $request);
    }

    #[Apidoc\Title("导出收据PDF")]
    #[Apidoc\Desc("生成充值或提现的电子凭证PDF")]
    #[Apidoc\Url("/admin/v1/export/receipt")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "订单类型(deposit充值,withdraw提现)")]
    #[Apidoc\Param(name: "order_id", type: "string", require: true, desc: "订单ID(hashid编码)")]
    public function receipt(Request $request)
    {
        $validator = validator($request->all(), [
            'type' => 'required|in:deposit,withdraw',
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $type = $request->input('type');

        if ($type === 'deposit') {
            $order = DepositOrder::with('user')->find($orderId);
        } else {
            $order = WithdrawOrder::with('user')->find($orderId);
        }

        if (!$order) {
            return $this->fail(trans('Order not found'), 404);
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: "DejaVu Sans", sans-serif; margin: 40px; }
            .header { text-align: center; border-bottom: 2px solid #1677FF; padding-bottom: 16px; margin-bottom: 24px; }
            .header h1 { color: #1677FF; font-size: 22px; }
            .info { margin: 16px 0; }
            .info td { padding: 6px 12px; }
            .info td:first-child { font-weight: bold; width: 140px; }
            .footer { text-align: center; font-size: 10px; color: #999; margin-top: 40px; border-top: 1px solid #eee; padding-top: 12px; }
        </style></head><body>
        <div class="header">
            <h1>' . ($type === 'deposit' ? trans('Deposit receipt') : trans('Withdrawal receipt')) . '</h1>
            <p>Global Game Platform</p>
        </div>
        <table class="info">
            <tr><td>' . trans('Order No.') . '</td><td>' . htmlspecialchars($order->order_no) . '</td></tr>
            <tr><td>' . trans('User') . '</td><td>' . htmlspecialchars($order->user->username ?? '') . '</td></tr>
            <tr><td>' . trans('Amount') . '</td><td>' . htmlspecialchars($type === 'deposit' ? $order->platform_amount : $order->platform_amount) . ' ' . trans('platform tokens') . '</td></tr>
            <tr><td>' . trans('Status') . '</td><td>' . htmlspecialchars($order->status) . '</td></tr>
            <tr><td>' . trans('Time') . '</td><td>' . ($order->created_at instanceof \DateTime ? $order->created_at->format('Y-m-d H:i:s') : $order->created_at) . '</td></tr>
        </table>
        <div class="footer">Copyright (c) 2026 erik — https://erik.xyz | ' . trans('Electronic receipt, equivalent to a paper receipt') . '</div>
        </body></html>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5');
        $dompdf->render();

        $filename = 'receipt_' . $order->order_no . '.pdf';
        $tmpFile = runtime_path() . '/tmp/' . $filename;
        $dir = dirname($tmpFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($tmpFile, $dompdf->output());

        return $this->downloadTemp($tmpFile, $filename, $request);
    }

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
            return [];
        }

        $model = new $modelMap[$table]();
        $query = $model->newQuery();

        foreach ($conditions as $field => $value) {
            if (!empty($value) || $value === '0') {
                $query->where($field, $value);
            }
        }

        return $query->limit(10000)->get()->toArray();
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
