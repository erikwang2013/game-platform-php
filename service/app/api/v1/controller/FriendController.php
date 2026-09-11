<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\model\Friend;
use common\model\User;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("好友")]
#[Apidoc\Group("friend")]
class FriendController extends BaseController
{
    #[Apidoc\Title("好友列表")]
    #[Apidoc\Url("/api/v1/friend/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Returned(name: "list", type: "array", desc: "好友列表，元素含 id/username/nickname/avatar")]
    public function list(Request $request): Response
    {
        $friends = Friend::where(static function($q) use ($request) {
            $q->where('user_id', $request->userId)->orWhere('friend_id', $request->userId);
        })->where('status', 'accepted')->with(['user', 'friendUser'])->get();

        $items = [];
        foreach ($friends as $f) {
            $other = $f->user_id === $request->userId ? $f->friendUser : $f->user;
            if (!$other) continue;
            $items[] = ['id' => $this->encodeId($other->id), 'username' => $other->username, 'nickname' => $other->nickname, 'avatar' => $other->avatar];
        }
        return $this->success(['list' => $items]);
    }

    #[Apidoc\Title("收到的好友申请列表")]
    #[Apidoc\Url("/api/v1/friend/requests")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Returned(name: "list", type: "array", desc: "待处理申请，元素含 id/user/created_at")]
    public function requests(Request $request): Response
    {
        $pending = Friend::where('friend_id', $request->userId)->where('status', 'pending')->with('user')->get();
        $items = [];
        foreach ($pending as $f) {
            if (!$f->user) continue;
            $items[] = ['id' => $this->encodeId($f->id), 'user' => ['id' => $this->encodeId($f->user->id), 'username' => $f->user->username, 'nickname' => $f->user->nickname, 'avatar' => $f->user->avatar], 'created_at' => $f->created_at];
        }
        return $this->success(['list' => $items]);
    }

    #[Apidoc\Title("发起好友申请")]
    #[Apidoc\Url("/api/v1/friend/request")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "friend_id", type: "string", require: true, desc: "好友用户ID(hashid)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "好友关系ID(hashid)")]
    public function request(Request $request): Response
    {
        $friendId = $this->decodeId($request->input('friend_id', '0'));
        if ($friendId <= 0 || $friendId === $request->userId) return $this->fail('Invalid friend', 422);
        if (!User::find($friendId)) return $this->fail('User not found', 404);

        $existing = Friend::where(static function($q) use ($request, $friendId) {
            $q->where('user_id', $request->userId)->where('friend_id', $friendId);
        })->orWhere(static function($q) use ($request, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $request->userId);
        })->first();
        if ($existing) return $this->fail('Already friends or request pending', 422);

        $f = new Friend();
        $f->id = $this->generateId();
        $f->user_id = $request->userId;
        $f->friend_id = $friendId;
        $f->status = 'pending';
        $f->created_at = date('Y-m-d H:i:s');
        $f->updated_at = date('Y-m-d H:i:s');
        $f->save();

        return $this->success(['id' => $this->encodeId($f->id)], 'Friend request sent');
    }

    #[Apidoc\Title("接受好友申请")]
    #[Apidoc\Url("/api/v1/friend/accept")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "request_id", type: "string", require: true, desc: "好友申请ID(hashid)")]
    public function accept(Request $request): Response
    {
        $reqId = $this->decodeId($request->input('request_id', '0'));
        $f = Friend::where('id', $reqId)->where('friend_id', $request->userId)->where('status', 'pending')->first();
        if (!$f) return $this->fail('Request not found', 404);
        $f->status = 'accepted';
        $f->updated_at = date('Y-m-d H:i:s');
        $f->save();
        return $this->success([], 'Friend request accepted');
    }

    #[Apidoc\Title("拒绝好友申请")]
    #[Apidoc\Url("/api/v1/friend/reject")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "request_id", type: "string", require: true, desc: "好友申请ID(hashid)")]
    public function reject(Request $request): Response
    {
        $reqId = $this->decodeId($request->input('request_id', '0'));
        $f = Friend::where('id', $reqId)->where('friend_id', $request->userId)->where('status', 'pending')->first();
        if (!$f) return $this->fail('Request not found', 404);
        $f->delete();
        return $this->success([], 'Friend request rejected');
    }

    #[Apidoc\Title("删除好友")]
    #[Apidoc\Url("/api/v1/friend/remove")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "friend_id", type: "string", require: true, desc: "好友用户ID(hashid)")]
    public function remove(Request $request): Response
    {
        $friendId = $this->decodeId($request->input('friend_id', '0'));
        Friend::where(static function($q) use ($request, $friendId) {
            $q->where('user_id', $request->userId)->where('friend_id', $friendId);
        })->orWhere(static function($q) use ($request, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $request->userId);
        })->where('status', 'accepted')->delete();
        return $this->success([], 'Friend removed');
    }

    #[Apidoc\Title("搜索用户")]
    #[Apidoc\Url("/api/v1/friend/search")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "q", type: "string", desc: "搜索关键词（用户名/昵称），为空时返回空列表")]
    #[Apidoc\Returned(name: "list", type: "array", desc: "匹配用户，最多 20 条，元素含 id/username/nickname/avatar")]
    public function search(Request $request): Response
    {
        $q = $request->input('q', '');
        if (empty(trim($q))) return $this->success(['list' => []]);
        $users = User::where('status', 1)->where('id', '!=', $request->userId)->where(static function($query) use ($q) {
            $query->where('username', 'like', "%{$q}%")->orWhere('nickname', 'like', "%{$q}%");
        })->limit(20)->get()->map(fn($u) => ['id' => $this->encodeId($u->id), 'username' => $u->username, 'nickname' => $u->nickname, 'avatar' => $u->avatar]);
        return $this->success(['list' => $users->toArray()]);
    }
}
