<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\Announcement;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("公告管理")
 * @Apidoc\Group("announcement")
 */
class AnnouncementController extends BaseController
{
    /**
     * @Apidoc\Title("公告列表")
     * @Apidoc\Desc("分页获取公告列表")
     * @Apidoc\Url("/admin/announcement/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="公告ID(hashid编码)")
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
     * @Apidoc\Title("发布公告")
     * @Apidoc\Desc("创建并发布一条新公告")
     * @Apidoc\Url("/admin/announcement/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("title", type="string", require=true, desc="公告标题")
     * @Apidoc\Param("content", type="string", require=true, desc="公告内容")
     * @Apidoc\Param("type", type="string", require=false, desc="公告类型(system系统,event活动)")
     * @Apidoc\Returned("id", type="string", desc="公告ID(hashid编码)")
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
