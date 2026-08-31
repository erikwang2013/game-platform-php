<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use app\model\Friend;
use common\model\User;
use support\Request;
use support\Response;

class FriendController extends BaseController
{
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

    public function reject(Request $request): Response
    {
        $reqId = $this->decodeId($request->input('request_id', '0'));
        $f = Friend::where('id', $reqId)->where('friend_id', $request->userId)->where('status', 'pending')->first();
        if (!$f) return $this->fail('Request not found', 404);
        $f->delete();
        return $this->success([], 'Friend request rejected');
    }

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
