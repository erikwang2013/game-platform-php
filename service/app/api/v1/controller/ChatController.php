<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\model\Friend;
use app\model\Message;
use support\Redis;
use support\Request;
use support\Response;
use hg\apidoc\annotation as Apidoc;

/**
 * @Apidoc\Title("聊天消息")
 * @Apidoc\Group("chat")
 */
class ChatController extends BaseController
{
    /**
     * @Apidoc\Title("会话列表")
     * @Apidoc\Url("/api/chat/conversations")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function conversations(Request $request): Response
    {
        $userId = $request->userId;
        $sent = Message::where('from_user_id', $userId)
            ->selectRaw('to_user_id as peer_id, MAX(id) as last_msg_id')
            ->groupBy('to_user_id')->pluck('last_msg_id', 'peer_id');
        $received = Message::where('to_user_id', $userId)
            ->selectRaw('from_user_id as peer_id, MAX(id) as last_msg_id')
            ->groupBy('from_user_id')->pluck('last_msg_id', 'peer_id');

        $conversations = [];
        $allPeers = $sent->union($received);
        foreach ($allPeers as $peerId => $lastMsgId) {
            $peerMsgId = max($sent[$peerId] ?? 0, $received[$peerId] ?? 0);
            $lastMsg = Message::find($peerMsgId);
            if (!$lastMsg) continue;
            $peer = \app\model\User::find($peerId);
            if (!$peer) continue;

            $unread = Message::where('to_user_id', $userId)
                ->where('from_user_id', $peerId)->where('is_read', 0)->count();

            $conversations[] = [
                'peer' => ['id' => $this->encodeId($peer->id), 'username' => $peer->username, 'nickname' => $peer->nickname, 'avatar' => $peer->avatar],
                'last_message' => mb_substr($lastMsg->content, 0, 100),
                'unread_count' => $unread,
                'updated_at' => $lastMsg->created_at,
            ];
        }
        usort($conversations, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
        return $this->success(['list' => $conversations]);
    }

    /**
     * @Apidoc\Title("消息列表")
     * @Apidoc\Url("/api/chat/messages/{peerHashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function messages(Request $request, string $peerHashid): Response
    {
        $userId = $request->userId;
        $peerId = $this->decodeId($peerHashid);
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 50);

        $msgs = Message::where(function($q) use ($userId, $peerId) {
            $q->where('from_user_id', $userId)->where('to_user_id', $peerId);
        })->orWhere(function($q) use ($userId, $peerId) {
            $q->where('from_user_id', $peerId)->where('to_user_id', $userId);
        })->orderBy('id', 'desc')
          ->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($msgs->items() as $m) {
            $items[] = [
                'id' => $this->encodeId($m->id),
                'from_user_id' => $this->encodeId($m->from_user_id),
                'to_user_id' => $this->encodeId($m->to_user_id),
                'content' => $m->content,
                'is_read' => $m->is_read,
                'created_at' => $m->created_at,
            ];
        }

        // Mark messages as read
        Message::where('to_user_id', $userId)->where('from_user_id', $peerId)
            ->where('is_read', 0)->update(['is_read' => 1]);

        return $this->success(['items' => array_reverse($items), 'total' => $msgs->total(), 'page' => $page, 'last_page' => $msgs->lastPage()]);
    }

    /**
     * @Apidoc\Title("发送消息")
     * @Apidoc\Url("/api/chat/send")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     */
    public function send(Request $request): Response
    {
        $userId = $request->userId;
        $peerId = $this->decodeId($request->input('to_user_id', '0'));
        $content = trim($request->input('content', ''));

        if ($peerId <= 0 || $userId === $peerId) return $this->fail('Invalid recipient', 422);
        if (empty($content) || mb_strlen($content) > 5000) return $this->fail('Message must be 1-5000 characters', 422);

        // Check friendship
        $friends = Friend::where(function($q) use ($userId, $peerId) {
            $q->where('user_id', $userId)->where('friend_id', $peerId);
        })->orWhere(function($q) use ($userId, $peerId) {
            $q->where('user_id', $peerId)->where('friend_id', $userId);
        })->where('status', 'accepted')->exists();
        if (!$friends) return $this->fail('Only friends can send messages', 403);

        $msg = new Message();
        $msg->id = $this->generateId();
        $msg->from_user_id = $userId;
        $msg->to_user_id = $peerId;
        $msg->content = $content;
        $msg->is_read = 0;
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->save();

        // Push via Redis to WebSocket process
        try {
            Redis::publish('chat:channel', json_encode([
                'type' => 'message',
                'message' => [
                    'id' => $this->encodeId($msg->id),
                    'from_user_id' => $this->encodeId($userId),
                    'content' => $content,
                    'created_at' => $msg->created_at,
                ],
                'to_user_id' => $peerId,
            ]));
        } catch (\Throwable $e) {}

        return $this->success(['id' => $this->encodeId($msg->id), 'created_at' => $msg->created_at], 'Sent');
    }

    /**
     * @Apidoc\Title("标记已读")
     * @Apidoc\Url("/api/chat/read")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     */
    public function markRead(Request $request): Response
    {
        $peerId = $this->decodeId($request->input('from_user_id', '0'));
        if ($peerId <= 0) return $this->fail('Invalid user', 422);
        Message::where('to_user_id', $request->userId)->where('from_user_id', $peerId)
            ->where('is_read', 0)->update(['is_read' => 1]);
        return $this->success([], 'Marked read');
    }

    /**
     * @Apidoc\Title("未读总数")
     * @Apidoc\Url("/api/chat/unread-total")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function unreadTotal(Request $request): Response
    {
        $count = Message::where('to_user_id', $request->userId)->where('is_read', 0)->count();
        return $this->success(['count' => $count]);
    }
}
