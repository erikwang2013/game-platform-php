<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Ticket;
use common\model\TicketReply;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("工单管理")
 * @Apidoc\Group("ticket")
 */
class TicketController extends BaseController
{
    /** @Apidoc\Title("工单列表") @Apidoc\Url("/admin/ticket/list") @Apidoc\Method("GET") */
    public function list(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 20);
        $status = $request->input('status', '');
        $type = $request->input('type', '');

        $query = Ticket::with('user')->orderBy('id', 'desc');
        if ($status) $query->where('status', $status);
        if ($type) $query->where('type', $type);

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->get()->map(function ($ticket) {
                $data = $ticket->toArray();
                $data['id'] = $this->encodeId($data['id']);
                $data['user_name'] = $ticket->user->username ?? 'N/A';
                $data['reply_count'] = $ticket->replies()->count();
                unset($data['user'], $data['user_id']);
                return $data;
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /** @Apidoc\Title("工单详情") @Apidoc\Url("/admin/ticket/{hashid}") @Apidoc\Method("GET") */
    public function detail(Request $request, string $hashid): Response
    {
        $ticket = Ticket::with(['replies', 'user'])->find($this->decodeId($hashid));
        if (!$ticket) return $this->fail('Ticket not found', 404);

        $replies = [];
        foreach ($ticket->replies as $reply) {
            $replies[] = [
                'id' => $this->encodeId($reply->id),
                'content' => $reply->content,
                'is_admin' => (int) $reply->is_admin,
                'created_at' => $reply->created_at,
            ];
        }

        return $this->success([
            'id' => $this->encodeId($ticket->id),
            'user_name' => $ticket->user->username ?? 'N/A',
            'type' => $ticket->type, 'subject' => $ticket->subject,
            'content' => $ticket->content, 'status' => $ticket->status,
            'priority' => $ticket->priority, 'assigned_to' => $ticket->assigned_to,
            'replies' => $replies, 'created_at' => $ticket->created_at,
        ]);
    }

    /** @Apidoc\Title("回复工单") @Apidoc\Url("/admin/ticket/{hashid}/reply") @Apidoc\Method("POST") */
    public function reply(Request $request, string $hashid): Response
    {
        $ticket = Ticket::find($this->decodeId($hashid));
        if (!$ticket) return $this->fail('Ticket not found', 404);
        if ($ticket->status === 'closed') return $this->fail('Ticket is closed', 422);

        $content = $request->input('content', '');
        if (empty($content)) return $this->fail('Content required', 422);

        $reply = new TicketReply();
        $reply->id = $this->generateId();
        $reply->ticket_id = $ticket->id;
        $reply->user_id = 0;
        $reply->content = $content;
        $reply->is_admin = 1;
        $reply->created_at = date('Y-m-d H:i:s');
        $reply->save();

        $ticket->status = 'replied';
        $ticket->save();

        return $this->success(['id' => $this->encodeId($reply->id)], 'Reply sent');
    }

    /** @Apidoc\Title("关闭工单") @Apidoc\Url("/admin/ticket/{hashid}/close") @Apidoc\Method("POST") */
    public function close(Request $request, string $hashid): Response
    {
        $ticket = Ticket::find($this->decodeId($hashid));
        if (!$ticket) return $this->fail('Ticket not found', 404);

        $ticket->status = 'closed';
        $ticket->resolved_at = date('Y-m-d H:i:s');
        $ticket->save();

        return $this->success([], 'Ticket closed');
    }

    /** @Apidoc\Title("指定处理人") @Apidoc\Url("/admin/ticket/{hashid}/assign") @Apidoc\Method("POST") */
    public function assign(Request $request, string $hashid): Response
    {
        $ticket = Ticket::find($this->decodeId($hashid));
        if (!$ticket) return $this->fail('Ticket not found', 404);

        $ticket->assigned_to = (int) $request->input('admin_id', 0);
        $ticket->save();

        return $this->success([], 'Assigned');
    }
}
