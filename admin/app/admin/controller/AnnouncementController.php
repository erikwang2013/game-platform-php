<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Announcement;
use support\Request;
use support\Response;

class AnnouncementController extends BaseController
{
    /**
     * 公告列表（分页）
     * GET /admin/announcement/list
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
     * POST /admin/announcement/create
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
