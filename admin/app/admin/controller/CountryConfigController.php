<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\CountryConfig;
use support\Request;
use support\Response;

class CountryConfigController extends BaseController
{
    /**
     * 国家配置列表（分页）
     * GET /admin/country/config/list
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
     * 创建国家配置
     * POST /admin/country/config/create
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
     * 更新国家配置
     * PUT /admin/country/config/{hashid}
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
