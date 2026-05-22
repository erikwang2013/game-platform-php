<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use app\common\EncryptionService;
use app\model\AdminUser;
use app\model\OperationLog;
use app\model\AdminRole;
use app\model\SystemConfig;
use common\model\User;
use common\model\Transaction;
use support\Request;

class ExportController extends BaseController
{
    /**
     * Excel 导出
     * POST /admin/export/excel
     */
    public function excel(Request $request): Response
    {
        $table = $request->input('table', 'admin_user');
        $columns = $request->input('columns', []);
        $conditions = $request->input('conditions', []);
        $title = $request->input('title', '数据导出');

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
                    $decrypted = EncryptionService::decrypt((string) $value);
                    if ($col === 'phone') {
                        $value = EncryptionService::maskPhone($decrypted);
                    } elseif ($col === 'email') {
                        $value = EncryptionService::maskEmail($decrypted);
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

        return response()->download($tmpFile, $filename);
    }

    /**
     * PDF 导出
     * POST /admin/export/pdf
     */
    public function pdf(Request $request): Response
    {
        $type = $request->input('type', 'table');
        $title = $request->input('title', '数据导出');
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
        $html .= '<div class="meta">导出时间: ' . $timestamp . '</div>';
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

        $html .= '<div class="footer">Copyright (c) 2026 erik — https://erik.xyz | 本文件包含不可移除的版权信息</div>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * 导出C端用户
     * POST /admin/export/users
     */
    public function exportUsers(Request $request): \Webman\Http\Response
    {
        $query = User::orderBy('id', 'desc');
        if ($request->has('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        $users = $query->limit(10000)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('用户列表');

        $headers = ['ID', '用户名', '昵称', '国家', '状态', '最后登录', '注册时间'];
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
            $sheet->getCell('E' . $row)->setValue($u->status == 1 ? '启用' : '禁用');
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

        return response()->download($tmpFile, $filename);
    }

    /**
     * 导出平台流水
     * POST /admin/export/transactions
     */
    public function exportTransactions(Request $request): \Webman\Http\Response
    {
        $query = Transaction::orderBy('created_at', 'desc');
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $transactions = $query->limit(10000)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('平台流水');

        $headers = ['ID', '用户ID', '类型', '金额', '余额', '关联类型', '备注', '时间'];
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

        return response()->download($tmpFile, $filename);
    }

    private function fetchExportData(string $table, array $columns, array $conditions): array
    {
        $modelMap = [
            'admin_user' => AdminUser::class,
            'operation_log' => OperationLog::class,
            'admin_role' => AdminRole::class,
            'system_config' => SystemConfig::class,
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
                'id' => '用户ID', 'username' => '用户名', 'real_name' => '真实姓名',
                'phone' => '手机号', 'email' => '邮箱', 'status' => '状态',
                'last_login_at' => '最后登录时间', 'last_login_ip' => '最后登录IP',
                'created_at' => '创建时间',
            ],
            'operation_log' => [
                'id' => 'ID', 'user_id' => '用户ID', 'action' => '操作动作',
                'method' => '请求方法', 'path' => '请求路径', 'ip' => 'IP地址',
                'created_at' => '操作时间',
            ],
            'admin_role' => [
                'id' => 'ID', 'name' => '角色名称', 'slug' => '角色标识',
                'description' => '描述', 'status' => '状态', 'created_at' => '创建时间',
            ],
            'system_config' => [
                'id' => 'ID', 'group' => '分组', 'key' => '配置键',
                'value' => '配置值', 'type' => '类型', 'description' => '说明',
                'created_at' => '创建时间',
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
