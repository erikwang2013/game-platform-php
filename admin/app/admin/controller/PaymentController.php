<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\PaymentMethod;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("支付管理")
 * @Apidoc\Group("payment")
 */
class PaymentController extends BaseController
{
    /**
     * @Apidoc\Title("支付方式列表")
     * @Apidoc\Desc("获取所有支付方式列表")
     * @Apidoc\Url("/admin/payment/method/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="支付方式ID(hashid编码)")
     */
    public function list(Request $request): Response
    {
        $list = PaymentMethod::orderBy('sort', 'asc')
                             ->get()
                             ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("启禁用支付方式")
     * @Apidoc\Desc("切换支付方式的启用/禁用状态")
     * @Apidoc\Url("/admin/payment/method/toggle")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("id", type="string", require=true, desc="支付方式ID(hashid编码)")
     * @Apidoc\Param("status", type="int", require=true, desc="状态(0禁用,1启用)")
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
