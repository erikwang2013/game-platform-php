<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\GameServer;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏服务器管理")
 * @Apidoc\Group("gameserver")
 */
class GameServerController extends BaseController
{
    /**
     * 游戏服务器列表
     * @Apidoc\Title("游戏服务器列表")
     * @Apidoc\Url("/admin/game/server/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     * @Apidoc\Param(name="game_id", type="string", required=false, desc="游戏ID")
     */
    public function list(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 15);
        $gameId = $request->input('game_id');

        $query = GameServer::query();
        if ($gameId) {
            $query->where('game_id', $this->decodeId($gameId));
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建游戏服务器
     * @Apidoc\Title("创建游戏服务器")
     * @Apidoc\Url("/admin/game/server/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="game_id", type="string", required=true, desc="游戏ID")
     * @Apidoc\Param(name="name", type="string", required=true, desc="服务器名称")
     * @Apidoc\Param(name="host", type="string", required=true, desc="服务器地址")
     * @Apidoc\Param(name="port", type="int", required=true, desc="端口")
     * @Apidoc\Param(name="region", type="string", required=false, desc="区域")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
            'name'    => 'required|string|max:100',
            'host'    => 'required|string|max:255',
            'port'    => 'required|integer|min:1|max:65535',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $server = new GameServer();
        $server->id      = $this->generateId();
        $server->game_id = $this->decodeId($request->input('game_id'));
        $server->name    = $request->input('name');
        $server->host    = $request->input('host');
        $server->port    = (int) $request->input('port');
        $server->region  = $request->input('region', '');
        $server->status  = (int) $request->input('status', 1);
        $server->save();

        return $this->success(['id' => $this->encodeId($server->id)], '创建成功');
    }

    /**
     * 更新游戏服务器
     * @Apidoc\Title("更新游戏服务器")
     * @Apidoc\Url("/admin/game/server/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="name", type="string", required=false, desc="服务器名称")
     * @Apidoc\Param(name="host", type="string", required=false, desc="服务器地址")
     * @Apidoc\Param(name="port", type="int", required=false, desc="端口")
     * @Apidoc\Param(name="region", type="string", required=false, desc="区域")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $server = GameServer::find($id);
        if (!$server) {
            return $this->fail('服务器不存在', 404);
        }

        $server->fill($request->only(['name', 'host', 'port', 'region', 'status']));
        $server->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏服务器
     * @Apidoc\Title("删除游戏服务器")
     * @Apidoc\Url("/admin/game/server/{hashid}")
     * @Apidoc\Method("DELETE")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $server = GameServer::find($id);
        if (!$server) {
            return $this->fail('服务器不存在', 404);
        }

        $server->delete();

        return $this->success([], '删除成功');
    }
}
