<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\admin\v1\ExportSupportTrait;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use common\EncryptionService;
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
    // 取数上限 / 截断信标 / 字段表 / 产物辅助搬到同命名空间的 trait：逐行原样搬移、零行为变更，
    // 只为守住 <500 行的仓库规矩（本文件只剩端点方法，注解随方法留在原处）。
    use ExportSupportTrait;

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
        [$data, $truncated] = $this->fetchExportData($table, $columns, $conditions);
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
            $colIndex = self::nextColumn($colIndex);
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
                $colIndex = self::nextColumn($colIndex);
            }
            $row++;
        }

        // 冻结首行
        $sheet->freezePane('A2');
        // 自动筛选
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        // 截断提示必须写在 setAutoFilter **之后**：否则提示行会被算进筛选区，Excel 里被当成一条数据
        if ($truncated) {
            $this->markTruncated($sheet, $row);
        }

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

        [$users, $truncated] = $this->fetchLimited($query);

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
            $col = self::nextColumn($col);
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
        if ($truncated) {
            $this->markTruncated($sheet, $row);
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

        [$transactions, $truncated] = $this->fetchLimited($query);

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
            $col = self::nextColumn($col);
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
        if ($truncated) {
            $this->markTruncated($sheet, $row);
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

}
