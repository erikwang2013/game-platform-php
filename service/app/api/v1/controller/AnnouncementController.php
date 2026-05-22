<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\Announcement;
use hg\apidoc\annotation as Apidoc;
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
     * @Apidoc\Url("/api/announcement/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        $list = Announcement::where('status', 1)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', date('Y-m-d H:i:s'));
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', date('Y-m-d H:i:s'));
            })
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $this->encodeId($a->id),
                    'title' => $a->title,
                    'type' => $a->type,
                    'created_at' => $a->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("公告详情")
     * @Apidoc\Url("/api/announcement/detail/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="公告hashid", in="path")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $a = Announcement::find($id);

        if (!$a || $a->status !== 1) {
            return $this->fail('公告不存在', 404);
        }

        return $this->success([
            'id' => $this->encodeId($a->id),
            'title' => $a->title,
            'content' => $a->content,
            'type' => $a->type,
            'created_at' => $a->created_at->format('Y-m-d H:i:s'),
        ]);
    }
}
