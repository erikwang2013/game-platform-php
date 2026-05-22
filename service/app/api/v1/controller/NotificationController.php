<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Notification;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("通知管理")
 * @Apidoc\Group("notification")
 */
class NotificationController extends BaseController
{
    /**
     * @Apidoc\Title("通知列表")
     * @Apidoc\Url("/api/notification/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function list(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $isRead  = $request->input('is_read');

        $query = Notification::where('user_id', $userId)
            ->orderBy('id', 'desc');

        if ($isRead !== null && $isRead !== '') {
            $query->where('is_read', (int) $isRead);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $notif) {
            $items[] = [
                'id'         => $this->encodeId($notif->id),
                'type'       => $notif->type,
                'title'      => $notif->title,
                'content'    => $notif->content,
                'is_read'    => $notif->is_read,
                'ref_type'   => $notif->ref_type,
                'ref_id'     => $notif->ref_id ? $this->encodeId($notif->ref_id) : null,
                'created_at' => $notif->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * @Apidoc\Title("未读数量")
     * @Apidoc\Url("/api/notification/unread-count")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function unreadCount(Request $request): Response
    {
        $count = Notification::where('user_id', $request->userId)
            ->where('is_read', 0)
            ->count();

        return $this->success(['count' => $count]);
    }

    /**
     * @Apidoc\Title("标记已读")
     * @Apidoc\Url("/api/notification/read")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="id", type="string", require=false, desc="通知ID(不传则全部已读)")
     */
    public function markRead(Request $request): Response
    {
        $userId = $request->userId;
        $hashId = $request->input('id');

        if ($hashId) {
            $notifId = $this->decodeId($hashId);
            $notif = Notification::where('user_id', $userId)
                ->where('id', $notifId)
                ->first();

            if (!$notif) {
                return $this->fail('Notification not found', 404);
            }

            $notif->is_read = 1;
            $notif->save();
        } else {
            Notification::where('user_id', $userId)
                ->where('is_read', 0)
                ->update(['is_read' => 1]);
        }

        return $this->success([], 'Marked as read');
    }
}
