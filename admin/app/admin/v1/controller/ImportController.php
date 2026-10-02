<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\model\AdminUser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use support\Request;
use support\Response;

#[Apidoc\Title("数据导入")]
#[Apidoc\Group("import")]
class ImportController extends BaseController
{
    /**
     * 行数上限（含表头）：超了**直接拒绝**，不静默只导前 N 行 ——
     * 「导了一半却报成功」比「明确报错」危险得多。注意 PhpSpreadsheet 对 zip bomb 有内建防护，
     * 这里量的是「业务上愿意吃多少行」，不是安全边界，别再叠一层自造校验。
     */
    private const MAX_ROWS = 1000;

    /** 错误明细条数上限：响应体没必要带 1000 条同样的话；截断与否由响应的 errors_truncated 说明 */
    private const MAX_ERRORS = 50;

    #[Apidoc\Title("导入用户")]
    #[Apidoc\Desc("通过Excel文件批量导入管理员用户")]
    #[Apidoc\Url("/admin/v1/import/users")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "file", type: "file", require: true, desc: "Excel文件(.xlsx/.xls)")]
    public function users(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail(trans('Please upload an Excel file'), 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail(trans('Only .xlsx or .xls files are supported'), 422);
        }

        $tmpPath = $file->getRealPath();
        // READ_DATA_ONLY：只取值，不解析样式/合并结构（导入用不到，且能显著压低大表的峰值内存）
        $spreadsheet = IOFactory::load($tmpPath, IReader::READ_DATA_ONLY);
        $sheet       = $spreadsheet->getActiveSheet();

        // 先量维度再 toArray()：超限要在**物化整表之前**拒绝（getHighestRow 只读已解析的维度）
        if ($sheet->getHighestRow() > self::MAX_ROWS) {
            return $this->fail(trans(
                'The Excel file has more than %max% rows, please split it into smaller files',
                ['%max%' => (string) self::MAX_ROWS]
            ), 422);
        }

        $rows        = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail(trans('The Excel file contains no data'), 422);
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap  = array_flip($headers);

        $required = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail(trans('Missing required column: %column%', ['%column%' => $col]), 422);
            }
        }

        $total   = 0;
        $success = 0;
        $failed  = 0;
        $errors  = [];

        // 已存在用户名**一次查完**，取代逐行 exists()（每行一条单行查询）。
        // 文件内重复也按「已存在」处理：原实现第二行会查到第一行刚插入的记录，
        // 故插入成功后把用户名补进集合，语义与之一致（否则会落到 uk_username 唯一键报错上）。
        $usernames = [];
        foreach ($rows as $idx => $row) {
            if ($idx === 0) continue;
            $name = trim((string) ($row[$colMap['username']] ?? ''));
            if ($name !== '') {
                $usernames[$name] = true;
            }
        }
        $taken = array_fill_keys(
            AdminUser::whereIn('username', array_keys($usernames))->pluck('username')->all(),
            true
        );

        foreach ($rows as $idx => $row) {
            if ($idx === 0) continue;
            $total++;

            $username = trim((string) ($row[$colMap['username']] ?? ''));
            $password = trim((string) ($row[$colMap['password']] ?? ''));
            $realName = trim((string) ($row[$colMap['real_name']] ?? ''));
            $phone    = trim((string) ($row[$colMap['phone']] ?? ''));
            $email    = trim((string) ($row[$colMap['email']] ?? ''));
            $status   = isset($colMap['status']) ? (int) ($row[$colMap['status']] ?? 1) : 1;

            if (empty($username)) {
                $failed++;
                $this->addError($errors, $idx + 1, trans('Username is empty'));
                continue;
            }

            if (strlen($password) < 8 || strlen($password) > 32 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
                $failed++;
                $this->addError($errors, $idx + 1, trans('Password must be 8-32 characters and contain uppercase, lowercase letters and digits'));
                continue;
            }

            if (isset($taken[$username])) {
                $failed++;
                $this->addError($errors, $idx + 1, trans('Username %username% already exists', ['%username%' => $username]));
                continue;
            }

            try {
                $user = new AdminUser();
                $user->id        = $this->generateId();
                $user->username  = $username;
                $user->password  = password_hash($password, PASSWORD_BCRYPT);
                $user->real_name = $realName;
                $user->status    = in_array($status, [0, 1], true) ? $status : 1;
                $user->phone     = $phone;
                $user->email     = $email;
                $user->save();
                $success++;
                $taken[$username] = true; // 文件内后续同名的行按「已存在」判，与逐行 exists() 语义一致
            } catch (\Throwable $e) {
                $failed++;
                $this->addError($errors, $idx + 1, $e->getMessage());
            }
        }

        return $this->success([
            'total'            => $total,
            'success'          => $success,
            'failed'           => $failed,
            'errors'           => $errors,
            'errors_truncated' => $failed > count($errors),
        ], trans('Import completed'));
    }

    /** 收集失败原因，封顶 MAX_ERRORS 条（failed 计数不受影响，超没超由 errors_truncated 说明） */
    private function addError(array &$errors, int $row, string $reason): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = ['row' => $row, 'reason' => $reason];
        }
    }
}
