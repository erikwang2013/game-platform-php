<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\model\Friend;
use common\model\Message;
use common\model\User;
use support\Redis;
use support\Request;
use support\Response;
use erikwang2013\apidoc\annotation as Apidoc;

#[Apidoc\Title("聊天消息")]
#[Apidoc\Group("chat")]
class ChatController extends BaseController
{
    /** 会话列表硬上限：按「最近一条消息」取最新的 N 个会话（会话数随使用单调增长，此前无上限） */
    private const CONVERSATION_LIMIT = 200;

    #[Apidoc\Title("会话列表")]
    #[Apidoc\Url("/api/v1/chat/conversations")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function conversations(Request $request): Response
    {
        $userId = $request->userId;
        // 每个方向先各取「最近 200 个 peer」（按该方向的最大消息 id）。两侧各限 200 是最终 200 条的
        // **超集**：若某 peer 的双向最大值取自 sent 而它不在 sent 的 top200，则已有 ≥200 个 peer 的
        // sent 最大值比它大 ⇒ 它本来也进不了全局 top200。合并后再切一次即得精确的全局 top200。
        // ⚠ 排序语义（下面 usort 的 updated_at desc）不动，这里限的是候选集。
        // ponytail: 上限 200，更早的会话不返回；要翻页再加 `last_msg_id` 游标入参
        // （当前三棵客户端树都没有消费者，加了＝零功能只生产误导信号）。
        $sent = Message::where('from_user_id', $userId)
            ->selectRaw('to_user_id as peer_id, MAX(id) as last_msg_id')
            ->groupBy('to_user_id')->orderByRaw('MAX(id) DESC')->limit(self::CONVERSATION_LIMIT)
            ->pluck('last_msg_id', 'peer_id');
        $received = Message::where('to_user_id', $userId)
            ->selectRaw('from_user_id as peer_id, MAX(id) as last_msg_id')
            ->groupBy('from_user_id')->orderByRaw('MAX(id) DESC')->limit(self::CONVERSATION_LIMIT)
            ->pluck('last_msg_id', 'peer_id');

        $conversations = [];
        // 合并双向、取双向最大值（＝最近一条消息 id，也是上面的游标键）。不用 union：union 只保留
        // 首个集合的 last_msg_id，会把「对方发来的更新」丢掉。
        $allPeers = [];
        foreach ([$sent, $received] as $side) {
            foreach ($side as $peerId => $lastMsgId) {
                $allPeers[$peerId] = max($allPeers[$peerId] ?? 0, (int) $lastMsgId);
            }
        }
        if (!$allPeers) {
            return $this->success(['list' => []]);
        }
        arsort($allPeers);   // 最近有消息的会话在前（切上限用，最终次序仍由下面 usort 定）
        $allPeers = array_slice($allPeers, 0, self::CONVERSATION_LIMIT, true);

        // 批量查询：消息/用户/未读数各 1 次，替代逐会话 3 次查询
        $peerIds = array_keys($allPeers);
        $lastMsgIds = array_values($allPeers);
        $msgs = Message::whereIn('id', $lastMsgIds)->get()->keyBy('id');
        $peers = User::whereIn('id', $peerIds)->get()->keyBy('id');
        $unread = Message::where('to_user_id', $userId)
            ->whereIn('from_user_id', $peerIds)->where('is_read', 0)
            ->selectRaw('from_user_id, COUNT(*) as c')
            ->groupBy('from_user_id')->pluck('c', 'from_user_id');

        foreach ($allPeers as $peerId => $lastMsgId) {
            $peerMsgId = $lastMsgId;   // 合并时已取双向最大值
            $lastMsg = $msgs->get($peerMsgId);
            if (!$lastMsg) continue;
            $peer = $peers->get($peerId);
            if (!$peer) continue;

            $conversations[] = [
                'peer' => ['id' => $this->encodeId($peer->id), 'username' => $peer->username, 'nickname' => $peer->nickname, 'avatar' => $peer->avatar],
                'last_message' => mb_substr($lastMsg->content, 0, 100),
                'unread_count' => (int) ($unread[$peerId] ?? 0),
                'updated_at' => $lastMsg->created_at,
            ];
        }
        usort($conversations, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
        return $this->success(['list' => $conversations]);
    }

    #[Apidoc\Title("消息列表")]
    #[Apidoc\Url("/api/v1/chat/messages/{peerHashid}")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function messages(Request $request, string $peerHashid): Response
    {
        $userId = $request->userId;
        $peerId = $this->decodeId($peerHashid);
        $page = (int) $request->input('page', 1);
        // 上下界都要夹，理由见 SearchController:28-31（负值会让 limit 子句整个消失 ⇒ 1064）
        $perPage = max(1, min(100, (int) $request->input('per_page', 50)));

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

    #[Apidoc\Title("发送消息")]
    #[Apidoc\Url("/api/v1/chat/send")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    public function send(Request $request): Response
    {
        $userId = $request->userId;
        $peerId = $this->decodeId($request->input('to_user_id', '0'));
        $content = trim($request->input('content', ''));

        if ($peerId <= 0 || $userId === $peerId) return $this->fail(trans('Invalid recipient'), 422);
        if (empty($content) || mb_strlen($content) > 5000) return $this->fail(trans('Message must be 1-5000 characters'), 422);

        // Check friendship
        // 两个 OR 方向必须整体成组：AND 比 OR 结合更紧，若写成 where(A)->orWhere(B)->where(status)
        // 会得到 `(A) OR (B AND status)`，A 分支不受 status 约束 ⇒ 一条 pending 申请即可发私信。
        $friends = Friend::where(function($q) use ($userId, $peerId) {
            $q->where('user_id', $userId)->where('friend_id', $peerId)
              ->orWhere(function($q) use ($peerId, $userId) {
                  $q->where('user_id', $peerId)->where('friend_id', $userId);
              });
        })->where('status', 'accepted')->exists();
        if (!$friends) return $this->fail(trans('Only friends can send messages'), 403);

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
            $payload = json_encode([
                'type' => 'message',
                'message' => [
                    'id' => $this->encodeId($msg->id),
                    'from_user_id' => $this->encodeId($userId),
                    'content' => $content,
                    'created_at' => $msg->created_at,
                ],
                'to_user_id' => $peerId,
            ]);
            Redis::lpush('chat:delivery_queue', $payload);
        } catch (\Throwable $e) {
            \support\Log::error('Chat realtime push degraded: ' . $e->getMessage());
        }

        return $this->success(['id' => $this->encodeId($msg->id), 'created_at' => $msg->created_at], 'Sent');
    }

    #[Apidoc\Title("标记已读")]
    #[Apidoc\Url("/api/v1/chat/read")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    public function markRead(Request $request): Response
    {
        $peerId = $this->decodeId($request->input('from_user_id', '0'));
        if ($peerId <= 0) return $this->fail(trans('Invalid user'), 422);
        Message::where('to_user_id', $request->userId)->where('from_user_id', $peerId)
            ->where('is_read', 0)->update(['is_read' => 1]);
        return $this->success([], trans('Marked read'));
    }

    #[Apidoc\Title("未读总数")]
    #[Apidoc\Url("/api/v1/chat/unread-total")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function unreadTotal(Request $request): Response
    {
        $count = Message::where('to_user_id', $request->userId)->where('is_read', 0)->count();
        return $this->success(['count' => $count]);
    }
}
