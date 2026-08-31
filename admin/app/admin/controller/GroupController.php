<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\Group;
use app\model\GroupMember;
use support\Request;
use support\Response;

/**
 * 组队/公会管理（M4）
 *
 * @Apidoc\Title("组队/公会管理")
 * @Apidoc\Group("group")
 */
class GroupController extends BaseController
{
    /**
     * @Apidoc\Title("组/公会列表")
     * @Apidoc\Url("/admin/groups")
     * @Apidoc\Method("GET")
     * @Apidoc\Param("type", type="string", require=false, desc="team/guild")
     * @Apidoc\Param("game_id", type="string", require=false, desc="游戏ID(hashid)")
     * @Apidoc\Param("status", type="int", require=false, desc="1=正常 0=解散")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("limit", type="int", require=false, desc="每页数量")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = Group::query();
        if ($request->input('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->input('game_id')) {
            $query->where('game_id', $this->decodeId($request->input('game_id')));
        }
        if ($request->input('status') !== null) {
            $query->where('status', (int) $request->input('status'));
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function (Group $group) {
                          $data = [
                              'id'           => $this->encodeId($group->id),
                              'type'         => $group->type,
                              'name'         => $group->name,
                              'game_id'      => $group->game_id > 0 ? $this->encodeId($group->game_id) : null,
                              'owner_id'     => $this->encodeId($group->owner_id),
                              'level'        => (int) $group->level,
                              'member_count' => (int) $group->member_count,
                              'expire_at'    => $group->expire_at,
                              'status'       => (int) $group->status,
                              'created_at'   => $group->created_at,
                          ];
                          return $data;
                      });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("成员变动流水")
     * @Apidoc\Url("/admin/groups/{hashid}/audit")
     * @Apidoc\Method("GET")
     * @Apidoc\Param("hashid", type="string", require=true, desc="组ID", in="path")
     */
    public function audit(Request $request, string $hashid): Response
    {
        $groupId = $this->decodeId($hashid);
        $group = Group::find($groupId);
        if (!$group) {
            return $this->fail('组不存在', 404);
        }

        $members = GroupMember::where('group_id', $groupId)
            ->orderBy('joined_at', 'desc')
            ->get()
            ->map(function (GroupMember $m) {
                return [
                    'user_id'   => $this->encodeId($m->user_id),
                    'role'      => $m->role,
                    'contrib'   => (int) $m->contrib,
                    'joined_at' => $m->joined_at,
                    'left_at'   => $m->left_at,
                ];
            });

        return $this->success([
            'group' => [
                'id'           => $this->encodeId($group->id),
                'type'         => $group->type,
                'name'         => $group->name,
                'status'       => (int) $group->status,
                'member_count' => (int) $group->member_count,
            ],
            'members' => $members,
        ]);
    }
}
