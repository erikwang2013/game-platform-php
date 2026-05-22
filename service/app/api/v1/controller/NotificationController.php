<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Notification;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Notification")
 * @Apidoc\Group("notification")
 */
class NotificationController extends BaseController
{
    /**
     * @Apidoc\Title("Notification List")
     * @Apidoc\Url("/api/notification/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     * @Apidoc\Query(name:"is_read",type:"integer",require:false,desc:"Read status filter (0=unread, 1=read)")
     */
    public function list(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $isRead  = $request->input('is_read');

        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($isRead !== null) {
            $query->where('is_read', (int) $isRead);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $notification) {
            $items[] = [
                'id'         => $this->encodeId($notification->id),
                'type'       => $notification->type,
                'title'      => $notification->title,
                'content'    => $notification->content,
                'is_read'    => (int) $notification->is_read,
                'read_at'    => $notification->read_at,
                'created_at' => $notification->created_at,
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
     * @Apidoc\Title("Unread Count")
     * @Apidoc\Url("/api/notification/unread-count")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function unreadCount(Request $request): Response
    {
        $userId = $request->userId;

        $count = Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();

        return $this->success(['count' => $count]);
    }

    /**
     * @Apidoc\Title("Mark Notification as Read")
     * @Apidoc\Url("/api/notification/read")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"id",type:"string",require:false,desc:"Notification hashid. Omit to mark all as read.")
     */
    public function markRead(Request $request): Response
    {
        $userId = $request->userId;
        $id     = $request->input('id');
        $now    = date('Y-m-d H:i:s');

        if ($id) {
            // Mark single notification as read
            $notificationId = $this->decodeId($id);

            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->first();

            if (!$notification) {
                return $this->fail('Notification not found', 404);
            }

            $notification->is_read = 1;
            $notification->read_at = $now;
            $notification->save();

            return $this->success([], 'Notification marked as read');
        }

        // Mark all unread notifications as read
        Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => $now,
            ]);

        return $this->success([], 'All notifications marked as read');
    }
}
