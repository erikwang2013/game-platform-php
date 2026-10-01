<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\Announcement;
use support\Request;
use support\Response;

#[Apidoc\Title("公告管理")]
#[Apidoc\Group("announcement")]
class AnnouncementController extends BaseController
{
    #[Apidoc\Title("公告列表")]
    #[Apidoc\Desc("分页获取公告列表")]
    #[Apidoc\Url("/admin/v1/announcement/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "公告ID(hashid编码)")]
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

    #[Apidoc\Title("发布公告")]
    #[Apidoc\Desc("创建并发布一条新公告")]
    #[Apidoc\Url("/admin/v1/announcement/create")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "title", type: "string", require: true, desc: "公告标题")]
    #[Apidoc\Param(name: "content", type: "string", require: true, desc: "公告内容")]
    #[Apidoc\Param(name: "type", type: "string", require: false, desc: "公告类型(system系统,event活动)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "公告ID(hashid编码)")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            // 与 update 同口径：枚举真值见列注释（system=系统 game=游戏 payment=支付）
            'type'        => 'sometimes|required|string|in:system,game,payment',
            'target_lang' => 'sometimes|nullable|string|max:10',
            'status'      => 'sometimes|required|integer|in:0,1',
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

        return $this->success(['id' => $this->encodeId($announcement->id)], trans('Created successfully'));
    }

    #[Apidoc\Title("更新公告")]
    #[Apidoc\Desc("局部更新：只改传了的字段")]
    #[Apidoc\Url("/admin/v1/announcement/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "title", type: "string", require: false, desc: "公告标题")]
    #[Apidoc\Param(name: "content", type: "string", require: false, desc: "公告内容")]
    #[Apidoc\Param(name: "type", type: "string", require: false, desc: "公告类型(system/event)")]
    #[Apidoc\Param(name: "status", type: "integer", require: false, desc: "状态(0下架/1上架)")]
    #[Apidoc\Param(name: "start_at", type: "string", require: false, desc: "生效时间")]
    #[Apidoc\Param(name: "end_at", type: "string", require: false, desc: "失效时间")]
    public function update(Request $request, string $hashid): Response
    {
        $announcement = Announcement::find($this->decodeId($hashid));
        if (!$announcement) {
            return $this->fail(trans('Announcement not found'), 404);
        }

        // 镜像 create 的规则，前缀 sometimes：update 是局部更新，缺省字段不该被判 required
        $validator = validator($request->all(), [
            'title'       => 'sometimes|required|string|max:255',
            'content'     => 'sometimes|required|string',
            // 枚举真值取自 game_announcement.type 的列注释与 install/test-data.sql 的存量数据（system/game/payment），
            // 不是 system/event —— 早期曾写错，会把存量里 type=payment 的公告一改就判 422
            'type'        => 'sometimes|required|string|in:system,game,payment',
            'status'      => 'sometimes|required|integer|in:0,1',
            'target_lang' => 'sometimes|nullable|string|max:10',
            'start_at'    => 'sometimes|nullable|date',
            'end_at'      => 'sometimes|nullable|date|after_or_equal:start_at',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        foreach (['title', 'content', 'type', 'target_lang'] as $field) {
            if ($request->input($field) !== null) {
                $announcement->{$field} = $request->input($field);
            }
        }
        if ($request->input('status') !== null) {
            $announcement->status = (int) $request->input('status');
        }
        foreach (['start_at', 'end_at'] as $field) {
            if ($request->has($field)) {
                $announcement->{$field} = $request->input($field) ?: null;
            }
        }
        $announcement->save();

        return $this->success($this->encodeIds($announcement->toArray()), trans('Updated successfully'));
    }

    #[Apidoc\Title("上架/下架公告")]
    #[Apidoc\Desc("状态变更：0 下架 / 1 上架")]
    #[Apidoc\Url("/admin/v1/announcement/toggle")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "id", type: "string", require: true, desc: "公告ID(hashid)")]
    #[Apidoc\Param(name: "status", type: "integer", require: true, desc: "目标状态(0/1)")]
    public function toggle(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id'     => 'required|string',
            'status' => 'required|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $announcement = Announcement::find($this->decodeId($request->input('id')));
        if (!$announcement) {
            return $this->fail(trans('Announcement not found'), 404);
        }

        $announcement->status = (int) $request->input('status');
        $announcement->save();

        return $this->success([], trans('Operation successful'));
    }

    #[Apidoc\Title("删除公告")]
    #[Apidoc\Url("/admin/v1/announcement/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    public function destroy(Request $request, string $hashid): Response
    {
        $announcement = Announcement::find($this->decodeId($hashid));
        if (!$announcement) {
            return $this->fail(trans('Announcement not found'), 404);
        }
        $announcement->delete();

        return $this->success([], trans('Deleted successfully'));
    }
}
