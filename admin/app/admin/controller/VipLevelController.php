<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\controller;
use common\model\VipLevel;
use common\model\UserVip;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("VIP等级管理")
 * @Apidoc\Group("vip")
 */
class VipLevelController extends BaseController
{
    /**
     * @Apidoc\Title("VIP等级列表")
     * @Apidoc\Url("/admin/v1/vip/level/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        $list = VipLevel::orderBy('level')->get()->map(function ($item) {
            $data = $item->toArray();
            return $this->encodeIds($data);
        });
        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("新增VIP等级")
     * @Apidoc\Url("/admin/v1/vip/level/create")
     * @Apidoc\Method("POST")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'level' => 'required|integer|min:0',
            'name' => 'required|string|max:50',
            'required_exp' => 'required|integer|min:0',
            'benefits' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $benefits = json_decode($request->input('benefits'), true);
        if (!is_array($benefits)) {
            return $this->fail('benefits must be valid JSON', 422);
        }

        $exists = VipLevel::where('level', $request->input('level'))->first();
        if ($exists) {
            return $this->fail('该VIP等级已存在', 422);
        }

        $vl = new VipLevel();
        $vl->id = $this->generateId();
        $vl->level = (int) $request->input('level');
        $vl->name = $request->input('name');
        $vl->required_exp = (int) $request->input('required_exp');
        $vl->benefits = $request->input('benefits');
        $vl->save();

        return $this->success($this->encodeIds($vl->toArray()), '创建成功');
    }

    /**
     * @Apidoc\Title("更新VIP等级")
     * @Apidoc\Url("/admin/v1/vip/level/{hashid}")
     * @Apidoc\Method("PUT")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $vl = VipLevel::find($id);
        if (!$vl) {
            return $this->fail('VIP等级不存在', 404);
        }

        $validator = validator($request->all(), [
            'name' => 'nullable|string|max:50',
            'required_exp' => 'nullable|integer|min:0',
            'benefits' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->has('benefits')) {
            $benefits = json_decode($request->input('benefits'), true);
            if (!is_array($benefits)) {
                return $this->fail('benefits must be valid JSON', 422);
            }
        }

        $vl->fill($request->only(['name', 'required_exp', 'benefits']));
        $vl->save();

        return $this->success($this->encodeIds($vl->toArray()), '更新成功');
    }

    /**
     * @Apidoc\Title("删除VIP等级")
     * @Apidoc\Url("/admin/v1/vip/level/{hashid}")
     * @Apidoc\Method("DELETE")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $vl = VipLevel::find($id);
        if (!$vl) {
            return $this->fail('VIP等级不存在', 404);
        }

        $userCount = UserVip::where('vip_level', $vl->level)->count();
        if ($userCount > 0) {
            return $this->fail("该VIP等级下有 {$userCount} 个用户，无法删除", 422);
        }

        $vl->delete();
        return $this->success([], '删除成功');
    }
}
