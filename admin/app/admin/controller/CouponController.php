<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Coupon;
use common\model\UserCoupon;
use support\Request;
use support\Response;

class CouponController extends BaseController
{
    /**
     * 优惠券列表
     * GET /admin/coupon/list
     */
    public function list(Request $request): Response
    {
        $page   = (int) $request->input('page', 1);
        $limit  = (int) $request->input('limit', 15);
        $status = $request->input('status');
        $type   = $request->input('type');
        $gameId = $request->input('game_id');

        $query = Coupon::query();

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($gameId) {
            $query->where('game_id', $this->decodeId($gameId));
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($coupon) {
                $data = $coupon->toArray();
                $data = $this->encodeIds($data);
                if (!empty($data['game_id']) && is_numeric($coupon->game_id) && $coupon->game_id > 0) {
                    $data['game_id'] = $this->encodeId((int) $coupon->game_id);
                }
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
     * 创建优惠券
     * POST /admin/coupon/create
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name'  => 'required|string|max:100',
            'type'  => 'required|string|in:fixed,rate',
            'value' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $coupon = new Coupon();
        $coupon->id          = $this->generateId();
        $coupon->name        = $request->input('name');
        $coupon->type        = $request->input('type');
        $coupon->value       = (string) $request->input('value');
        $coupon->min_amount  = (string) $request->input('min_amount', '0.0000');
        $coupon->max_discount = (string) $request->input('max_discount', '0.0000');

        $gameId = $request->input('game_id');
        if ($gameId) {
            $coupon->game_id = $this->decodeId($gameId);
        } else {
            $coupon->game_id = 0;
        }

        $coupon->total_qty  = (int) $request->input('total_qty', 0);
        $coupon->user_limit = (int) $request->input('user_limit', 1);
        $coupon->start_at   = $request->input('start_at') ?: null;
        $coupon->end_at     = $request->input('end_at') ?: null;
        $coupon->status     = 1;
        $coupon->save();

        return $this->success(['id' => $this->encodeId($coupon->id)], '创建成功');
    }

    /**
     * 更新优惠券
     * PUT /admin/coupon/{hashid}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        if ((int) $coupon->used_qty > 0) {
            return $this->fail('该优惠券已有用户领取，无法修改', 400);
        }

        $data = $request->only([
            'name', 'type', 'value', 'min_amount', 'max_discount',
            'total_qty', 'user_limit', 'start_at', 'end_at', 'status',
        ]);

        if (isset($data['value'])) {
            $data['value'] = (string) $data['value'];
        }
        if (isset($data['min_amount'])) {
            $data['min_amount'] = (string) $data['min_amount'];
        }
        if (isset($data['max_discount'])) {
            $data['max_discount'] = (string) $data['max_discount'];
        }

        $gameId = $request->input('game_id');
        if ($gameId !== null) {
            $data['game_id'] = $this->decodeId($gameId);
        }

        $coupon->fill($data);
        $coupon->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除优惠券
     * DELETE /admin/coupon/{hashid}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        // 删除所有该优惠券的用户领取记录
        UserCoupon::where('coupon_id', $coupon->id)->delete();

        $coupon->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 优惠券统计
     * GET /admin/coupon/{hashid}/stats
     */
    public function stats(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        $totalQty  = (int) $coupon->total_qty;
        $usedQty   = (int) $coupon->used_qty;
        $remaining = $totalQty > 0 ? $totalQty - $usedQty : null;
        $usageRate = $totalQty > 0 ? round($usedQty / $totalQty * 100, 2) : 0;

        return $this->success([
            'total_qty'  => $totalQty,
            'used_qty'   => $usedQty,
            'remaining'  => $remaining,
            'usage_rate' => $usageRate,
        ]);
    }
}
