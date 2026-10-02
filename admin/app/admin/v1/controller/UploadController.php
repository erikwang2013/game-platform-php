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
 * ⚠ 墓碑（2026-10-02 裁决）：本端点**保留不删** —— 用户 2026-10-02 裁决「保留；修掉三处描述漂移，
 * 并把裁决记进墓碑」。记在这里，这个议题就不再被反复提出。
 *
 * 裁决依据的读数：
 *   · **零前端消费者**：四棵管理端树（apps/react、apps/angular、apps/flutter、apps/harmonyos）
 *     一律走 `/admin/v1/aetherupload/*`，没有一处拼出 `/admin/v1/upload`。
 *   · **写是要求鉴权的**：`config/route.php:110` 的 Route::post('/upload', …) 就在 `/admin/v1` 组里
 *     （组起 `:73`、收在 `:314-317`），挂 AdminAuth + AdminPermission + OperationLog。
 *   · **公开的是「读」**：文件经 `public_path()` 落进 Web 根（`$absoluteDir` + `$file->move()`），响应 `url` 即该相对路径（`$relativePath`）；
 *     `config/static.php` 是 `enable=true`，而 webman 的 `findFile()` 排在 `findRoute()` **之前**
 *     （vendor/workerman/webman-framework/src/App.php:165-167），静态回调又以 `$withGlobalMiddleware=false`
 *     注册（同文件 `:988`；`config/static.php` 的 middleware 列表为空）⇒ 取文件**不进路由、也不进中间件**。
 *
 * 代价（裁决已知悉）：**任何人都能拿 URL 直取已上传文件**，唯一遮蔽是文件名不可猜
 * （`md5(uniqid(mt_rand(), true))`）；扩展名白名单 + 10MB 上限都不做内容嗅探。
 * 对照：aetherupload 落在非公开目录、经 `/display/{savedPath}` 取。
 *
 * 若日后要删，这四处要一起摘（只删本文件会留下 500「类不存在」；写法可参照 `config/route.php:330` 那条 register 墓碑）：
 *   ① `config/route.php:110` 的 Route::post('/upload', …)
 *   ② `tests/api/admin_test.php:106` 的跳过硬编码（该文件在**仓根** tests/ 下，不在 admin/tests/ 下）
 *   ③ 端点表 39 处：`README.*.md` ×13 恒在 `:388`、`docs/API.*.md` ×13 恒在 `:1524`、`docs/DESIGN.*.md` ×13 恒在 `:190`
 *   ④ `install/install.sql:1254` 的 RBAC 种子 slug `post.admin/upload`（+ `:1224` 菜单行）——
 *      漏摘会被 `admin/tests/PermissionSeedParityTest` 的「运行时 ⇄ 种子」双向差集打红
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
    #[Apidoc\Param(name: "file", type: "file", require: true, desc: "上传文件(jpg/jpeg/png/gif/pdf/xlsx/docx, 最大10MB)")]
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
