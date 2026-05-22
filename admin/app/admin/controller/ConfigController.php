<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\SystemConfig;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("系统配置")
 * @Apidoc\Group("config")
 */
class ConfigController extends BaseController
{
    /**
     * 配置列表
     * @Apidoc\Title("配置列表")
     * @Apidoc\Url("/admin/config")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param(name="group", type="string", require=false, desc="配置分组")
     */
    public function index(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $group = $request->input('group', '');

        $query = SystemConfig::query();
        if ($group !== '') {
            $query->where('group', $group);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('group')
                       ->orderBy('key')
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
     * 创建配置
     * @Apidoc\Title("创建配置")
     * @Apidoc\Url("/admin/config")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="group", type="string", require=true, desc="配置分组")
     * @Apidoc\Param(name="key", type="string", require=true, desc="配置键")
     * @Apidoc\Param(name="value", type="string", require=true, desc="配置值")
     * @Apidoc\Param(name="type", type="string", require=false, desc="值类型")
     * @Apidoc\Param(name="description", type="string", require=false, desc="说明")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'group' => 'required|string|max:100',
            'key'   => 'required|string|max:100',
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = SystemConfig::where('group', $request->input('group'))
                              ->where('key', $request->input('key'))
                              ->exists();
        if ($exists) {
            return $this->fail('配置项已存在', 422);
        }

        $config = new SystemConfig();
        $config->id          = $this->generateId();
        $config->group       = $request->input('group');
        $config->key         = $request->input('key');
        $config->value       = $request->input('value');
        $config->type        = $request->input('type', 'string');
        $config->description = $request->input('description', '');
        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '创建成功');
    }

    /**
     * 更新配置
     * @Apidoc\Title("更新配置")
     * @Apidoc\Url("/admin/config/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="配置哈希ID")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        if ($request->has('value')) {
            $config->value = $request->input('value');
        }
        if ($request->has('type')) {
            $config->type = $request->input('type');
        }
        if ($request->has('description')) {
            $config->description = $request->input('description');
        }

        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '更新成功');
    }

    /**
     * 删除配置
     * @Apidoc\Title("删除配置")
     * @Apidoc\Url("/admin/config/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="配置哈希ID")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $config->delete();
        return $this->success([], '删除成功');
    }
}
