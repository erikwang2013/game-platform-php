<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use support\Request;
use support\Response;

class ImportController extends BaseController
{
    public function users(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('请上传 Excel 文件', 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('仅支持 .xlsx 或 .xls 文件', 422);
        }

        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail('Excel 文件无数据', 422);
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap  = array_flip($headers);

        $required = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail("缺少必填列: {$col}", 422);
            }
        }

        $total   = 0;
        $success = 0;
        $failed  = 0;
        $errors  = [];

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
                $errors[] = ['row' => $idx + 1, 'reason' => '用户名为空'];
                continue;
            }

            if (AdminUser::where('username', $username)->exists()) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => "用户名 {$username} 已存在"];
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
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => $e->getMessage()];
            }
        }

        return $this->success([
            'total'   => $total,
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ], '导入完成');
    }
}
