<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\controller;
use common\model\Achievement;
use support\Request;
use support\Response;

class AchievementController extends BaseController
{
    public function list(Request $request): Response
    {
        $list = Achievement::orderBy('id')->get()->map(function ($item) {
            $data = $item->toArray();
            return $this->encodeIds($data);
        });
        return $this->success(['list' => $list]);
    }

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
            return $this->fail('condition_json must be valid JSON', 422);
        }

        if (Achievement::where('key', $request->input('key'))->exists()) {
            return $this->fail('该成就key已存在', 422);
        }

        $a = new Achievement();
        $a->id = $this->generateId();
        $a->key = $request->input('key');
        $a->name = $request->input('name');
        $a->description = $request->input('description', '');
        $a->icon = $request->input('icon', '');
        $a->condition_json = $request->input('condition_json');
        $a->points = (int) $request->input('points');
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), '创建成功');
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $a = Achievement::find($id);
        if (!$a) {
            return $this->fail('成就不存在', 404);
        }

        if ($request->has('condition_json')) {
            $cond = json_decode($request->input('condition_json'), true);
            if (!is_array($cond)) {
                return $this->fail('condition_json must be valid JSON', 422);
            }
        }

        $a->fill($request->only(['name', 'description', 'icon', 'condition_json', 'points']));
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $a = Achievement::find($id);
        if (!$a) {
            return $this->fail('成就不存在', 404);
        }

        $a->delete();
        return $this->success([], '删除成功');
    }
}
