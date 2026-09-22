<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\DepositOrder;
use common\model\PaymentMethod;
use support\Request;
use support\Response;

#[Apidoc\Title("支付管理")]
#[Apidoc\Group("payment")]
class PaymentController extends BaseController
{
    #[Apidoc\Title("支付方式列表")]
    #[Apidoc\Desc("获取所有支付方式列表")]
    #[Apidoc\Url("/admin/v1/payment/method/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "支付方式ID(hashid编码)")]
    public function list(Request $request): Response
    {
        $list = PaymentMethod::orderBy('sort', 'asc')
                             ->get()
                             ->map(function ($item) {
                                 $row = $this->encodeIds($item->toArray());

                                 // config 列经 cast 解密后已还原为 array；本端点的对外线格式仍是
                                 // JSON 文本（Flutter 端按文本回填，吐对象会被 Dart 的 Map.toString()
                                 // 拼成非法 JSON 而静默存坏配置）——此处保住原线格式，契约零变化
                                 $config = $row['config'] ?? null;
                                 $row['config'] = is_array($config)
                                     ? ($config === [] ? '{}' : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                                     : $config;

                                 return $row;
                             });

        return $this->success(['list' => $list]);
    }

    #[Apidoc\Title("启禁用支付方式")]
    #[Apidoc\Desc("切换支付方式的启用/禁用状态")]
    #[Apidoc\Url("/admin/v1/payment/method/toggle")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "id", type: "string", require: true, desc: "支付方式ID(hashid编码)")]
    #[Apidoc\Param(name: "status", type: "int", require: true, desc: "状态(0禁用,1启用)")]
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

    #[Apidoc\Title("创建支付方式")]
    #[Apidoc\Desc("创建支付方式（含国家可见性/金额区间/币种限定）")]
    #[Apidoc\Url("/admin/v1/payment/method/create")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: true, desc: "支付方式名称")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "类型(fiat/crypto)")]
    #[Apidoc\Param(name: "provider", type: "string", require: true, desc: "提供商(stripe/nowpayments/coinbase/paypal/skrill/neteller/paysafecard/paytm/mercadopago/astropay/paypay/kakaopay/gcash/mpesa/paystack/toss/adyen/grabpay)")]
    #[Apidoc\Param(name: "status", type: "int", require: true, desc: "状态(0禁用,1启用)")]
    #[Apidoc\Param(name: "countries", type: "array", require: false, desc: "可见国家码数组(空=全球)")]
    #[Apidoc\Param(name: "config", type: "string", require: false, desc: "支付配置JSON(加密存储)")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name'        => 'required|string|max:50',
            'type'        => 'required|in:fiat,crypto',
            'provider'    => 'required|in:stripe,nowpayments,coinbase,paypal,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash,mpesa,paystack,toss,adyen,grabpay',
            'status'      => 'required|in:0,1',
            'sort'        => 'integer|min:0',
            'countries'   => 'array',
            'countries.*' => 'string|max:2',
            'currency'    => 'string|max:10',
            'min_amount'  => 'numeric|min:0',
            'max_amount'  => 'numeric|min:0',
            'config'      => 'string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $method = new PaymentMethod();
        $method->id         = $this->generateId();
        $method->name       = $request->input('name');
        $method->type       = $request->input('type');
        $method->provider   = $request->input('provider');
        $method->status     = (int) $request->input('status');
        $method->sort       = (int) $request->input('sort', 0);
        $method->countries  = $request->input('countries', []);
        $method->currency   = (string) $request->input('currency', '');
        $method->min_amount = (string) $request->input('min_amount', '0');
        $method->max_amount = (string) $request->input('max_amount', '0');
        $method->config     = $request->input('config', '') !== '' ? (string) $request->input('config') : null;
        $method->save();

        return $this->success(['id' => $this->encodeId($method->id)], '创建成功');
    }

    #[Apidoc\Title("更新支付方式")]
    #[Apidoc\Desc("更新支付方式，仅更新传入字段")]
    #[Apidoc\Url("/admin/v1/payment/method/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $method = PaymentMethod::find($id);
        if (!$method) {
            return $this->fail('支付方式不存在', 404);
        }

        $validator = validator($request->all(), [
            'name'        => 'string|max:50',
            'type'        => 'in:fiat,crypto',
            'provider'    => 'in:stripe,nowpayments,coinbase,paypal,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash,mpesa,paystack,toss,adyen,grabpay',
            'status'      => 'in:0,1',
            'sort'        => 'integer|min:0',
            'countries'   => 'array',
            'countries.*' => 'string|max:2',
            'currency'    => 'string|max:10',
            'min_amount'  => 'numeric|min:0',
            'max_amount'  => 'numeric|min:0',
            'config'      => 'string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        foreach (['name', 'type', 'provider', 'status', 'sort', 'countries', 'currency', 'min_amount', 'max_amount', 'config'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                if ($field === 'config' && $value === '') {
                    $value = null; // 与 create() 一致：空配置存 NULL 而非空串
                }
                $method->$field = in_array($field, ['status', 'sort'], true) ? (int) $value : $value;
            }
        }
        $method->save();

        return $this->success([], '更新成功');
    }

    #[Apidoc\Title("删除支付方式")]
    #[Apidoc\Desc("删除支付方式；存在待支付订单时拒绝")]
    #[Apidoc\Url("/admin/v1/payment/method/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    public function delete(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $method = PaymentMethod::find($id);
        if (!$method) {
            return $this->fail('支付方式不存在', 404);
        }

        if (DepositOrder::where('payment_method_id', $id)->where('status', 'pending')->exists()) {
            return $this->fail('存在待支付订单，无法删除', 422);
        }

        $method->delete();

        return $this->success([], '删除成功');
    }
}
