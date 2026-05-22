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
 * @Apidoc\Title("国家配置管理")
 * @Apidoc\Group("country_config")
 */
class CountryConfigController extends BaseController
{
    /**
     * 国家配置列表
     * @Apidoc\Title("国家配置列表")
     * @Apidoc\Url("/admin/country/config/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = CountryConfig::query();
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
     * 创建国家配置
     * @Apidoc\Title("创建国家配置")
     * @Apidoc\Url("/admin/country/config/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="country_code", type="string", required=true, desc="国家代码")
     * @Apidoc\Param(name="country_name", type="string", required=true, desc="国家名称")
     * @Apidoc\Param(name="currency", type="string", required=true, desc="货币代码")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'country_code' => 'required|string|max:10',
            'country_name' => 'required|string|max:100',
            'currency'     => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if (CountryConfig::where('country_code', $request->input('country_code'))->exists()) {
            return $this->fail('该国家配置已存在', 422);
        }

        $config = new CountryConfig();
        $config->id           = $this->generateId();
        $config->country_code = $request->input('country_code');
        $config->country_name = $request->input('country_name');
        $config->currency     = $request->input('currency');
        $config->status       = (int) $request->input('status', 1);
        $config->save();

        return $this->success(['id' => $this->encodeId($config->id)], '创建成功');
    }

    /**
     * 更新国家配置
     * @Apidoc\Title("更新国家配置")
     * @Apidoc\Url("/admin/country/config/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="country_name", type="string", required=false, desc="国家名称")
     * @Apidoc\Param(name="currency", type="string", required=false, desc="货币代码")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = CountryConfig::find($id);
        if (!$config) {
            return $this->fail('配置不存在', 404);
        }

        $config->fill($request->only(['country_name', 'currency', 'status']));
        $config->save();

        return $this->success([], '更新成功');
    }
}
