<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * C端 - 通知
 *
 * @Apidoc\Title("通知")
 * @Apidoc\Group("notification")
 */
class NotificationController extends BaseController
{
    /**
     * @Apidoc\Title("通知列表")
     * @Apidoc\Url("/api/notification/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function list(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        return $this->success([
            'items'     => [],
            'total'     => 0,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => 0,
        ]);
    }

    /**
     * @Apidoc\Title("未读通知数")
     * @Apidoc\Url("/api/notification/unread-count")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function unreadCount(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success(['count' => 0]);
    }

    /**
     * @Apidoc\Title("标记已读")
     * @Apidoc\Url("/api/notification/read")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"ids",type:"array",require:false,desc:"通知ID列表，不传则全部已读")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function markRead(Request $request): Response
    {
        $userId = $request->userId;
        $ids    = $request->input('ids');

        return $this->success([], 'Marked as read');
    }
}
