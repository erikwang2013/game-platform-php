<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Announcement;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("公告管理")
 * @Apidoc\Group("announcement")
 */
class AnnouncementController extends BaseController
{
    /**
     * 公告列表（分页）
     * @Apidoc\Title("公告列表")
     * @Apidoc\Url("/admin/announcement/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页数量")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $total = Announcement::query()->count();
        $list = Announcement::offset(($page - 1) * $limit)
                            ->limit($limit)
                            ->orderBy('id', 'desc')
                            ->get()
                            ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建公告
     * @Apidoc\Title("创建公告")
     * @Apidoc\Url("/admin/announcement/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="title", type="string", require=true, desc="公告标题")
     * @Apidoc\Param(name="content", type="string", require=true, desc="公告内容")
     * @Apidoc\Param(name="type", type="string", require=false, desc="公告类型")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $announcement = new Announcement();
        $announcement->id          = $this->generateId();
        $announcement->title       = $request->input('title');
        $announcement->content     = $request->input('content');
        $announcement->type        = $request->input('type', 'system');
        $announcement->target_lang = $request->input('target_lang', '');
        $announcement->status      = (int) $request->input('status', 1);
        $announcement->start_at    = $request->input('start_at', null);
        $announcement->end_at      = $request->input('end_at', null);
        $announcement->save();

        return $this->success(['id' => $this->encodeId($announcement->id)], '创建成功');
    }
}
