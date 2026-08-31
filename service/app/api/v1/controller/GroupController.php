<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Game;
use app\model\Group;
use app\model\GroupMember;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

/**
 * 组队/公会（M4）：同表双形态，type=team 短时组队 / type=guild 长期公会。
 * 权限规则：team 任意成员可解散（expire_at 到期自动）；guild 仅 owner 可解散，转让（PUT /{id}/transfer）不在本期。
 *
 * @Apidoc\Title("组队/公会")
 * @Apidoc\Group("group")
 */
class GroupController extends BaseController
{
    /**
     * @Apidoc\Title("创建组/公会")
     * @Apidoc\Url("/api/groups")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="type", type="string", require=true, desc="team/guild")
     * @Apidoc\Param(name="name", type="string", require=true, desc="名称")
     * @Apidoc\Param(name="game_id", type="string", require=false, desc="归属游戏(hashid，team 必填)")
     * @Apidoc\Param(name="expire_at", type="string", require=false, desc="到期时间(team 可传)")
     * @Apidoc\Param(name="announcement", type="string", require=false, desc="公告(guild)")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'type' => 'required|string|in:team,guild',
            'name' => 'required|string|max:100',
            'game_id' => 'sometimes|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $type = $request->input('type');
        $gameId = 0;
        if ($request->input('game_id')) {
            $gameId = $this->decodeId($request->input('game_id'));
            if (!Game::where('id', $gameId)->where('status', 1)->exists()) {
                return $this->fail('Game not found', 422);
            }
        }
        if ($type === 'team' && $gameId <= 0) {
            return $this->fail('game_id required for team', 422);
        }

        $userId = $request->userId;
        $groupId = $this->generateId();
        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($request, $type, $gameId, $userId, $groupId, $now) {
            $group = new Group();
            $group->id = $groupId;
            $group->type = $type;
            $group->name = trim($request->input('name'));
            $group->game_id = $gameId;
            $group->owner_id = $userId;
            $group->member_count = 1;
            $group->announcement = $type === 'guild' ? (string) $request->input('announcement', '') : '';
            if ($type === 'team' && $request->input('expire_at')) {
                $group->expire_at = $request->input('expire_at');
            }
            $group->save();

            $member = new GroupMember();
            $member->id = $this->generateId();
            $member->group_id = $groupId;
            $member->user_id = $userId;
            $member->role = 'owner';
            $member->joined_at = $now;
            $member->save();
        });

        return $this->success(['id' => $this->encodeId($groupId)], 'Created');
    }

    /**
     * @Apidoc\Title("组/公会详情")
     * @Apidoc\Url("/api/groups/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="组ID", in="path")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $group = Group::find($this->decodeId($hashid));
        if (!$group) {
            return $this->fail('Group not found', 404);
        }

        $data = $this->toData($group);

        return $this->success($data);
    }

    /**
     * @Apidoc\Title("成员列表")
     * @Apidoc\Url("/api/groups/{hashid}/members")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="组ID", in="path")
     * @Apidoc\Param(name="sort", type="string", require=false, desc="contrib=按贡献值倒序(默认)")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页条数")
     */
    public function members(Request $request, string $hashid): Response
    {
        $groupId = $this->decodeId($hashid);
        $group = Group::find($groupId);
        if (!$group) {
            return $this->fail('Group not found', 404);
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $sort = $request->input('sort', 'contrib');

        $query = GroupMember::where('group_id', $groupId)
            ->whereNull('left_at')
            ->orderBy($sort === 'contrib' ? 'contrib' : 'joined_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $m) {
            $items[] = [
                'user_id'   => $this->encodeId($m->user_id),
                'role'      => $m->role,
                'contrib'   => (int) $m->contrib,
                'joined_at' => $m->joined_at,
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
     * @Apidoc\Title("加入组/公会")
     * @Apidoc\Url("/api/groups/{hashid}/join")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="组ID", in="path")
     */
    public function join(Request $request, string $hashid): Response
    {
        $groupId = $this->decodeId($hashid);
        $userId = $request->userId;

        $result = Db::transaction(function () use ($groupId, $userId) {
            $group = Group::where('id', $groupId)->lockForUpdate()->first();
            if (!$group || (int) $group->status !== 1) {
                return ['code' => 404, 'msg' => 'Group not found or dissolved'];
            }
            if ($group->expire_at && strtotime($group->expire_at) < time()) {
                return ['code' => 410, 'msg' => 'Group expired'];
            }

            $member = new GroupMember();
            $member->id = $this->generateId();
            $member->group_id = $groupId;
            $member->user_id = $userId;
            $member->role = 'member';
            $member->joined_at = date('Y-m-d H:i:s');
            try {
                $member->save();
            } catch (\PDOException $e) {
                if (in_array($e->errorInfo[1] ?? null, [1062, 23000], true)) {
                    return ['code' => 422, 'msg' => 'Already a member'];
                }
                throw $e;
            }

            $group->member_count = (int) $group->member_count + 1;
            $group->save();

            return null;
        });

        if ($result !== null) {
            return $this->fail($result['msg'], $result['code']);
        }

        return $this->success([], 'Joined');
    }

    /**
     * @Apidoc\Title("退出/解散")
     * @Apidoc\Url("/api/groups/{hashid}/leave")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="组ID", in="path")
     */
    public function leave(Request $request, string $hashid): Response
    {
        $groupId = $this->decodeId($hashid);
        $userId = $request->userId;

        $result = Db::transaction(function () use ($groupId, $userId, $request) {
            $group = Group::where('id', $groupId)->lockForUpdate()->first();
            if (!$group || (int) $group->status !== 1) {
                return ['code' => 404, 'msg' => 'Group not found or dissolved'];
            }

            $member = GroupMember::where('group_id', $groupId)
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();
            if (!$member) {
                return ['code' => 422, 'msg' => 'Not a member'];
            }

            // guild 仅 owner 可解散：owner 退出即解散公会（转让不在本期）；team 任意成员可解散
            if ($member->role === 'owner' && $group->type === 'guild') {
                return ['code' => 422, 'msg' => 'Owner cannot leave, dissolve the guild instead'];
            }

            $now = date('Y-m-d H:i:s');
            $dissolve = $member->role === 'owner' || ($member->role !== 'owner' && $group->type === 'team' && (bool) $request->input('dissolve', false));

            if ($dissolve) {
                $group->status = 0;
                GroupMember::where('group_id', $groupId)->whereNull('left_at')->update(['left_at' => $now]);
                $group->member_count = 0;
                $group->save();
                return ['dissolve' => true];
            }

            $member->left_at = $now;
            $member->save();
            $group->member_count = max(0, (int) $group->member_count - 1);
            $group->save();

            return ['dissolve' => false];
        });

        if (isset($result['code'])) {
            return $this->fail($result['msg'], $result['code']);
        }

        return $this->success([], $result['dissolve'] ? 'Dissolved' : 'Left');
    }

    /**
     * @Apidoc\Title("成员角色变更")
     * @Apidoc\Url("/api/groups/{hashid}/role")
     * @Apidoc\Method("PUT")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="组ID", in="path")
     * @Apidoc\Param(name="user_id", type="string", require=true, desc="目标用户(hashid)")
     * @Apidoc\Param(name="role", type="string", require=true, desc="admin/member")
     */
    public function role(Request $request, string $hashid): Response
    {
        $validator = validator($request->all(), [
            'user_id' => 'required|string',
            'role'    => 'required|string|in:admin,member',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $groupId = $this->decodeId($hashid);
        $userId = $request->userId;
        $targetId = $this->decodeId($request->input('user_id'));
        $newRole = $request->input('role');

        $result = Db::transaction(function () use ($groupId, $userId, $targetId, $newRole) {
            $group = Group::where('id', $groupId)->lockForUpdate()->first();
            if (!$group || (int) $group->status !== 1) {
                return ['code' => 404, 'msg' => 'Group not found or dissolved'];
            }

            $actor = GroupMember::where('group_id', $groupId)->where('user_id', $userId)->whereNull('left_at')->first();
            $target = GroupMember::where('group_id', $groupId)->where('user_id', $targetId)->whereNull('left_at')->first();
            if (!$actor || !$target) {
                return ['code' => 422, 'msg' => 'Not a member'];
            }
            if (!in_array($actor->role, ['owner', 'admin'], true)) {
                return ['code' => 403, 'msg' => 'Owner or admin only'];
            }
            if ($target->role === 'owner') {
                return ['code' => 422, 'msg' => 'Cannot change owner role (transfer is not in scope)'];
            }
            // admin 只能操作 member 级成员
            if ($actor->role === 'admin' && $target->role !== 'member') {
                return ['code' => 403, 'msg' => 'Admin can only manage members'];
            }

            $target->role = $newRole;
            $target->save();

            return null;
        });

        if ($result !== null) {
            return $this->fail($result['msg'], $result['code']);
        }

        return $this->success([], 'Role updated');
    }

    private function toData(Group $group): array
    {
        return [
            'id'           => $this->encodeId($group->id),
            'type'         => $group->type,
            'name'         => $group->name,
            'game_id'      => $group->game_id > 0 ? $this->encodeId($group->game_id) : null,
            'owner_id'     => $this->encodeId($group->owner_id),
            'level'        => (int) $group->level,
            'xp'           => (int) $group->xp,
            'member_count' => (int) $group->member_count,
            'announcement' => $group->announcement,
            'expire_at'    => $group->expire_at,
            'status'       => (int) $group->status,
            'created_at'   => $group->created_at,
        ];
    }
}
