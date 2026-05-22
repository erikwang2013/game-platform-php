<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use common\model\GameServer;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏区服管理")
 * @Apidoc\Group("gameserver")
 */
class GameServerController extends BaseController
{
    /**
     * 区服列表
     * @Apidoc\Title("区服列表")
     * @Apidoc\Url("/admin/game/server/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏哈希ID")
     */
    public function list(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $game = Game::find($gameId);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $servers = GameServer::where('game_id', $gameId)
            ->orderBy('region', 'asc')
            ->orderBy('sort', 'asc')
            ->get()
            ->map(function ($server) {
                $data = $server->toArray();
                return $this->encodeIds($data);
            });

        return $this->success($servers);
    }

    /**
     * 创建区服
     * @Apidoc\Title("创建区服")
     * @Apidoc\Url("/admin/game/server/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏哈希ID")
     * @Apidoc\Param(name="name", type="string", require=true, desc="区服名称")
     * @Apidoc\Param(name="region", type="string", require=false, desc="区域")
     * @Apidoc\Param(name="status", type="int", require=false, desc="状态: 0=禁用, 1=启用")
     * @Apidoc\Param(name="sort", type="int", require=false, desc="排序")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
            'name'    => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $game = Game::find($gameId);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $server = new GameServer();
        $server->id      = $this->generateId();
        $server->game_id = $gameId;
        $server->name    = $request->input('name');
        $server->region  = $request->input('region', '');
        $server->status  = (int) $request->input('status', 1);
        $server->sort    = (int) $request->input('sort', 0);
        $server->save();

        return $this->success(['id' => $this->encodeId($server->id)], '创建成功');
    }

    /**
     * 更新区服
     * @Apidoc\Title("更新区服")
     * @Apidoc\Url("/admin/game/server/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="区服哈希ID")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $server = GameServer::find($id);
        if (!$server) {
            return $this->fail('区服不存在', 404);
        }

        $server->fill($request->only(['name', 'region', 'status', 'sort']));
        $server->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除区服
     * @Apidoc\Title("删除区服")
     * @Apidoc\Url("/admin/game/server/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="区服哈希ID")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $server = GameServer::find($id);
        if (!$server) {
            return $this->fail('区服不存在', 404);
        }

        $server->delete();

        return $this->success([], '删除成功');
    }
}
