<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Ticket;
use common\model\TicketReply;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("工单")]
#[Apidoc\Group("ticket")]
class TicketController extends BaseController
{
    #[Apidoc\Title("工单列表")]
    #[Apidoc\Url("/api/v1/ticket/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "page", type: "int", default: 1, desc: "页码")]
    #[Apidoc\Query(name: "per_page", type: "int", default: 20, desc: "每页条数")]
    #[Apidoc\Returned(name: "items", type: "array", desc: "工单列表，元素含 id/type/subject/status/priority/reply_count/created_at")]
    #[Apidoc\Returned(name: "total", type: "int", desc: "总条数")]
    #[Apidoc\Returned(name: "page", type: "int", desc: "当前页码")]
    #[Apidoc\Returned(name: "last_page", type: "int", desc: "最后一页页码")]
    public function list(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $tickets = Ticket::where('user_id', $request->userId)
            ->withCount('replies')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($tickets->items() as $ticket) {
            $items[] = [
                'id' => $this->encodeId($ticket->id),
                'type' => $ticket->type,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'reply_count' => $ticket->replies_count,
                'created_at' => $ticket->created_at,
            ];
        }

        return $this->success([
            'items' => $items,
            'total' => $tickets->total(),
            'page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
        ]);
    }

    #[Apidoc\Title("工单详情")]
    #[Apidoc\Url("/api/v1/ticket/{hashid}")]
    #[Apidoc\Method("GET")]
    #[Apidoc\RouteParam(name: "hashid", type: "string", require: true, desc: "工单ID(hashid)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "工单ID(hashid)")]
    #[Apidoc\Returned(name: "type", type: "string", desc: "工单类型")]
    #[Apidoc\Returned(name: "subject", type: "string", desc: "标题")]
    #[Apidoc\Returned(name: "content", type: "string", desc: "内容")]
    #[Apidoc\Returned(name: "status", type: "string", desc: "状态")]
    #[Apidoc\Returned(name: "priority", type: "int", desc: "优先级")]
    #[Apidoc\Returned(name: "replies", type: "array", desc: "回复列表，元素含 id/content/is_admin/created_at")]
    #[Apidoc\Returned(name: "created_at", type: "string", desc: "创建时间")]
    public function detail(Request $request, string $hashid): Response
    {
        $ticket = Ticket::with('replies')->find($this->decodeId($hashid));
        if (!$ticket || $ticket->user_id !== $request->userId) {
            return $this->fail('Ticket not found', 404);
        }

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
            'type' => $ticket->type,
            'subject' => $ticket->subject,
            'content' => $ticket->content,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'replies' => $replies,
            'created_at' => $ticket->created_at,
        ]);
    }

    #[Apidoc\Title("创建工单")]
    #[Apidoc\Url("/api/v1/ticket/create")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "工单类型：deposit/withdraw/game/account/other")]
    #[Apidoc\Param(name: "subject", type: "string", require: true, desc: "标题（最长 200）")]
    #[Apidoc\Param(name: "content", type: "string", require: true, desc: "内容（最长 5000）")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "工单ID(hashid)")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'type' => 'required|string|in:deposit,withdraw,game,account,other',
            'subject' => 'required|string|max:200',
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $ticket = new Ticket();
        $ticket->id = $this->generateId();
        $ticket->user_id = $request->userId;
        $ticket->type = $request->input('type');
        $ticket->subject = $request->input('subject');
        $ticket->content = $request->input('content');
        $ticket->status = 'open';
        $ticket->priority = 0;
        $ticket->save();

        return $this->success(['id' => $this->encodeId($ticket->id)], 'Ticket created');
    }

    #[Apidoc\Title("回复工单")]
    #[Apidoc\Url("/api/v1/ticket/{hashid}/reply")]
    #[Apidoc\Method("POST")]
    #[Apidoc\RouteParam(name: "hashid", type: "string", require: true, desc: "工单ID(hashid)")]
    #[Apidoc\Param(name: "content", type: "string", require: true, desc: "回复内容（最长 5000）")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "回复ID(hashid)")]
    public function reply(Request $request, string $hashid): Response
    {
        $ticket = Ticket::find($this->decodeId($hashid));
        if (!$ticket || $ticket->user_id !== $request->userId) {
            return $this->fail('Ticket not found', 404);
        }
        if ($ticket->status === 'closed') {
            return $this->fail('Ticket is closed', 422);
        }

        $validator = validator($request->all(), [
            'content' => 'required|string|max:5000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $reply = new TicketReply();
        $reply->id = $this->generateId();
        $reply->ticket_id = $ticket->id;
        $reply->user_id = $request->userId;
        $reply->content = $request->input('content');
        $reply->is_admin = 0;
        $reply->created_at = date('Y-m-d H:i:s');
        $reply->save();

        $ticket->status = 'waiting';
        $ticket->save();

        return $this->success(['id' => $this->encodeId($reply->id)], 'Reply sent');
    }
}
