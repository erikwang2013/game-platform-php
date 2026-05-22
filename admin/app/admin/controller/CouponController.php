<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Coupon;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("优惠券管理")
 * @Apidoc\Group("coupon")
 */
class CouponController extends BaseController
{
    /**
     * 优惠券列表
     * @Apidoc\Title("优惠券列表")
     * @Apidoc\Url("/admin/coupon/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function list(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 15);
        $status = $request->input('status');

        $query = Coupon::query();
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
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
     * 创建优惠券
     * @Apidoc\Title("创建优惠券")
     * @Apidoc\Url("/admin/coupon/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", required=true, desc="优惠券名称")
     * @Apidoc\Param(name="type", type="string", required=true, desc="类型:fixed|percent")
     * @Apidoc\Param(name="value", type="float", required=true, desc="面值/折扣")
     * @Apidoc\Param(name="min_amount", type="float", required=false, desc="最低使用金额")
     * @Apidoc\Param(name="total_count", type="int", required=true, desc="发行数量")
     * @Apidoc\Param(name="start_at", type="string", required=false, desc="开始时间")
     * @Apidoc\Param(name="end_at", type="string", required=false, desc="结束时间")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name'        => 'required|string|max:100',
            'type'        => 'required|string|in:fixed,percent',
            'value'       => 'required|numeric|min:0',
            'total_count' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $coupon = new Coupon();
        $coupon->id          = $this->generateId();
        $coupon->name        = $request->input('name');
        $coupon->type        = $request->input('type');
        $coupon->value       = $request->input('value');
        $coupon->min_amount  = $request->input('min_amount', '0');
        $coupon->total_count = (int) $request->input('total_count');
        $coupon->remain_count = (int) $request->input('total_count');
        $coupon->start_at    = $request->input('start_at');
        $coupon->end_at      = $request->input('end_at');
        $coupon->status      = (int) $request->input('status', 1);
        $coupon->save();

        return $this->success(['id' => $this->encodeId($coupon->id)], '创建成功');
    }

    /**
     * 更新优惠券
     * @Apidoc\Title("更新优惠券")
     * @Apidoc\Url("/admin/coupon/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="name", type="string", required=false, desc="名称")
     * @Apidoc\Param(name="total_count", type="int", required=false, desc="发行数量")
     * @Apidoc\Param(name="start_at", type="string", required=false, desc="开始时间")
     * @Apidoc\Param(name="end_at", type="string", required=false, desc="结束时间")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        $coupon->fill($request->only(['name', 'total_count', 'start_at', 'end_at', 'status']));
        $coupon->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除优惠券
     * @Apidoc\Title("删除优惠券")
     * @Apidoc\Url("/admin/coupon/{hashid}")
     * @Apidoc\Method("DELETE")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        $coupon->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 优惠券统计
     * @Apidoc\Title("优惠券统计")
     * @Apidoc\Url("/admin/coupon/{hashid}/stats")
     * @Apidoc\Method("GET")
     */
    public function stats(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        $usedCount = $coupon->total_count - $coupon->remain_count;

        return $this->success([
            'id'           => $this->encodeId($coupon->id),
            'name'         => $coupon->name,
            'total_count'  => $coupon->total_count,
            'remain_count' => $coupon->remain_count,
            'used_count'   => $usedCount,
            'usage_rate'   => $coupon->total_count > 0 ? round($usedCount / $coupon->total_count * 100, 1) : 0,
        ]);
    }
}
