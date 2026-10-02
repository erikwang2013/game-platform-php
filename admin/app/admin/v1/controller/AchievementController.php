<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\v1\controller;
use erikwang2013\apidoc\annotation as Apidoc;
use common\model\Achievement;
use support\Request;
use support\Response;

#[Apidoc\Title("成就管理")]
#[Apidoc\Group("achievement")]
class AchievementController extends BaseController
{
    #[Apidoc\Title("成就列表")]
    #[Apidoc\Url("/admin/v1/achievement/list")]
    #[Apidoc\Method("GET")]
    public function list(Request $request): Response
    {
        $list = Achievement::orderBy('id')->get()->map(function ($item) {
            $data = $item->toArray();
            return $this->encodeIds($data);
        });
        return $this->success(['list' => $list]);
    }

    #[Apidoc\Title("新增成就")]
    #[Apidoc\Url("/admin/v1/achievement/create")]
    #[Apidoc\Method("POST")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'key' => 'required|string|regex:/^[a-z0-9_]+$/|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:200',
            'condition_json' => 'required|string',
            'points' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $cond = json_decode($request->input('condition_json'), true);
        if (!is_array($cond)) {
            return $this->fail(trans('condition_json must be valid JSON'), 422);
        }

        if (Achievement::where('key', $request->input('key'))->exists()) {
            return $this->fail(trans('Achievement key already exists'), 422);
        }

        $a = new Achievement();
        $a->id = $this->generateId();
        $a->key = $request->input('key');
        $a->name = $request->input('name');
        $a->description = $request->input('description', '');
        $a->icon = $request->input('icon', '');
        $a->condition_json = $request->input('condition_json');
        $a->points = (int) $request->input('points');
        $a->status = (int) $request->input('status', 1);
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), trans('Created successfully'));
    }

    #[Apidoc\Title("更新成就")]
    #[Apidoc\Url("/admin/v1/achievement/{hashid}")]
    #[Apidoc\Method("PUT")]
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $a = Achievement::find($id);
        if (!$a) {
            return $this->fail(trans('Achievement not found'), 404);
        }

        // 镜像 store 的规则，前缀 sometimes：update 是局部更新，缺省的字段不该被判 required。
        // 键与值域同样按 game_achievement 的列定义收口（points INT UNSIGNED ⇒ min:0）。
        $validator = validator($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'icon' => 'sometimes|nullable|string|max:255',
            'condition_json' => 'sometimes|required|string',
            'points' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->has('condition_json')) {
            $cond = json_decode($request->input('condition_json'), true);
            if (!is_array($cond)) {
                return $this->fail(trans('condition_json must be valid JSON'), 422);
            }
        }

        $a->fill($request->only(['name', 'description', 'icon', 'condition_json', 'points', 'status']));
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), trans('Updated successfully'));
    }

    /**
     * 上架/停用成就。
     *
     * 停用只影响「后续事件触发是否再授予」——消费方 AchievementService 已加 status 过滤；
     * 已授予的记录与用户进度不受影响（删定义才会丢历史，见迁移注释）。
     */
    #[Apidoc\Title("上架/停用成就")]
    #[Apidoc\Url("/admin/v1/achievement/toggle")]
    #[Apidoc\Method("POST")]
    public function toggle(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id'     => 'required|string',
            'status' => 'required|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $a = Achievement::find($this->decodeId($request->input('id')));
        if (!$a) {
            return $this->fail(trans('Achievement not found'), 404);
        }

        $a->status = (int) $request->input('status');
        $a->save();

        return $this->success([], trans('Operation successful'));
    }

    #[Apidoc\Title("删除成就")]
    #[Apidoc\Url("/admin/v1/achievement/{hashid}")]
    #[Apidoc\Method("DELETE")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $a = Achievement::find($id);
        if (!$a) {
            return $this->fail(trans('Achievement not found'), 404);
        }

        $a->delete();
        return $this->success([], trans('Deleted successfully'));
    }
}
