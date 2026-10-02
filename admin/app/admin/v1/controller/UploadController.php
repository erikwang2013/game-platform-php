<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * ⚠ 墓碑（2026-10-02 复核）：本端点**零前端消费者**，四棵管理端树（apps/react、apps/angular、
 * apps/flutter、apps/harmonyos）一律走 `/admin/v1/aetherupload/*`，没有一处拼出 `/admin/v1/upload`。
 * 没有当场删掉，是因为「路由 + 引用文件」全在本次改动范围之外，删控制器只会把 500（类不存在）留在原地：
 *   ① `config/route.php:110` 的 Route::post('/upload', …)（该目录另有代理在改）
 *   ② `tests/api/admin_test.php:106` 的白名单
 *   ③ `README.*.md` ×13 与 `docs/API.*.md` / `docs/DESIGN.*.md` 共 26 处端点表
 * 删的时候三处要一起摘（可参照 config/route.php:330 那条 register 墓碑的写法）。
 *
 * 顺带记一笔与本文件存亡有关的口径：这里用 `public_path()` 落盘（文件放在 Web 根下、可直接 GET），
 * 而 aetherupload 落在非公开目录、经 `/display/{savedPath}` 取；扩展名白名单 + 10MB 上限都不做内容嗅探。
 * 所以「留着不删」等于把一个可公开直取的写口留在路由表里 —— 裁决该端点去留时这是主要理由。
 */
#[Apidoc\Title("文件上传")]
#[Apidoc\Group("upload")]
class UploadController extends BaseController
{
    private array $allowExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'docx'];
    private int $maxSize = 10 * 1024 * 1024;

    #[Apidoc\Title("文件上传")]
    #[Apidoc\Desc("上传文件到服务器，支持图片、文档等格式")]
    #[Apidoc\Url("/admin/v1/upload")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "file", type: "file", require: true, desc: "上传文件(jpg/png/gif/pdf/xlsx/docx, 最大10MB)")]
    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file) {
            return $this->fail(trans('Please select a file'), 422);
        }

        if (!$file->isValid()) {
            return $this->fail(trans('File upload failed'), 500);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'bin');
        if (!in_array($ext, $this->allowExts, true)) {
            return $this->fail(trans('Unsupported file type: .') . $ext, 422);
        }

        if ($file->getSize() > $this->maxSize) {
            return $this->fail(trans('File size must not exceed 10MB'), 422);
        }

        $dateDir  = date('Y-m-d');
        $filename = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $relativePath = "/upload/{$dateDir}/{$filename}";
        $absoluteDir  = public_path() . "/upload/{$dateDir}";

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir . '/' . $filename);

        return $this->success(['url' => $relativePath], trans('Uploaded successfully'));
    }
}
