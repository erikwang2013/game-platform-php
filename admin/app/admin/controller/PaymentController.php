<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\PaymentMethod;
use support\Request;
use support\Response;

class PaymentController extends BaseController
{
    /**
     * 支付方式列表
     * GET /admin/payment/list
     */
    public function list(Request $request): Response
    {
        $list = PaymentMethod::orderBy('sort', 'asc')
                             ->get()
                             ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * 切换支付方式状态
     * POST /admin/payment/method/toggle
     */
    public function toggle(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id'     => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $id     = $this->decodeId($request->input('id'));
        $method = PaymentMethod::find($id);
        if (!$method) {
            return $this->fail('支付方式不存在', 404);
        }

        $method->status = (int) $request->input('status');
        $method->save();

        return $this->success([], '操作成功');
    }
}
