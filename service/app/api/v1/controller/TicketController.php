<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\Ticket;
use app\model\TicketReply;
use support\Request;
use support\Response;

class TicketController extends BaseController
{
    public function list(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $tickets = Ticket::where('user_id', $request->userId)
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
                'reply_count' => $ticket->replies()->count(),
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
