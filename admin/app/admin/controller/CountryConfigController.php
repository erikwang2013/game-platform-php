<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\CountryConfig;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("国家配置")
 * @Apidoc\Group("country_config")
 */
class CountryConfigController extends BaseController
{
    /**
     * @Apidoc\Title("国家配置列表")
     * @Apidoc\Desc("分页获取国家配置列表")
     * @Apidoc\Url("/admin/country/config/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="配置ID(hashid编码)")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = CountryConfig::orderBy('country_code', 'asc');

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->get()
                       ->map(function ($config) {
                           $data = $config->toArray();
                           return $this->encodeIds($data);
                       });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建国家配置")
     * @Apidoc\Desc("创建一个新的国家/地区配置")
     * @Apidoc\Url("/admin/country/config/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("country_code", type="string", require=true, desc="国家代码(ISO 3166-1 alpha-2)")
     * @Apidoc\Param("currency", type="string", require=true, desc="货币代码(ISO 4217)")
     * @Apidoc\Param("payment_methods", type="string", require=false, desc="支付方式JSON数组")
     * @Apidoc\Param("withdraw_methods", type="string", require=false, desc="提现方式JSON数组")
     * @Apidoc\Param("min_deposit", type="float", require=false, desc="最低充值金额")
     * @Apidoc\Returned("id", type="string", desc="配置ID(hashid编码)")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'country_code' => 'required|string|size:2',
            'currency'     => 'required|string|size:3',
            'payment_methods'  => 'nullable|string',
            'withdraw_methods' => 'nullable|string',
            'min_deposit'  => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $config = new CountryConfig();
        $config->id               = $this->generateId();
        $config->country_code     = strtoupper($request->input('country_code'));
        $config->currency         = strtoupper($request->input('currency'));
        $config->payment_methods  = $request->input('payment_methods', '[]');
        $config->withdraw_methods = $request->input('withdraw_methods', '[]');
        $config->min_deposit      = $request->input('min_deposit', '1.0000');
        $config->status           = 1;
        $config->save();

        return $this->success(['id' => $this->encodeId($config->id)], '创建成功');
    }

    /**
     * @Apidoc\Title("编辑国家配置")
     * @Apidoc\Desc("更新国家/地区配置信息")
     * @Apidoc\Url("/admin/country/config/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = CountryConfig::find($id);
        if (!$config) {
            return $this->fail('配置不存在', 404);
        }

        $config->fill($request->only([
            'currency', 'payment_methods', 'withdraw_methods', 'min_deposit', 'status',
        ]));
        $config->save();

        return $this->success([], '更新成功');
    }
}
