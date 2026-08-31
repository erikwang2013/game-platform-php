<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\common\CdnProbeService;
use common\model\CdnProvider;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("CDN 管理")
 * @Apidoc\Group("cdn")
 */
class CdnProviderController extends BaseController
{
    /**
     * @Apidoc\Title("CDN 厂商列表")
     * @Apidoc\Desc("获取所有 CDN 厂商配置")
     * @Apidoc\Url("/admin/cdn/provider/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="厂商ID(hashid编码)")
     */
    public function list(Request $request): Response
    {
        $list = CdnProvider::orderBy('sort', 'asc')
                           ->get()
                           ->map(function ($item) {
                               $data = $item->toArray();
                               unset($data['config']); // 凭据不回传
                               return $this->encodeIds($data);
                           });

        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("启停 CDN 厂商")
     * @Apidoc\Desc("切换 CDN 厂商的启用/禁用状态")
     * @Apidoc\Url("/admin/cdn/provider/toggle")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("id", type="string", require=true, desc="厂商ID(hashid编码)")
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

        $provider = CdnProvider::find($this->decodeId($request->input('id')));
        if (!$provider) {
            return $this->fail('CDN厂商不存在', 404);
        }

        $provider->status = (int) $request->input('status');
        $provider->save();

        return $this->success([], '操作成功');
    }

    /**
     * @Apidoc\Title("创建 CDN 厂商")
     * @Apidoc\Desc("创建 CDN 厂商配置")
     * @Apidoc\Url("/admin/cdn/provider/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=true, desc="显示名称")
     * @Apidoc\Param("provider", type="string", require=true, desc="厂商(cloudflare/cloudfront/aliyun/tencent/huawei)")
     * @Apidoc\Param("config", type="string", require=false, desc="配置JSON(加密存储)")
     * @Apidoc\Param("status", type="int", require=true, desc="状态(0禁用,1启用)")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name'     => 'required|string|max:50',
            'provider' => 'required|in:cloudflare,cloudfront,aliyun,tencent,huawei',
            'config'   => 'nullable|string',
            'status'   => 'required|in:0,1',
            'sort'     => 'integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $providerName = $request->input('provider');
        if (CdnProvider::where('provider', $providerName)->exists()) {
            return $this->fail("厂商 {$providerName} 已存在", 422);
        }
        $config = $request->input('config', '');
        if ($config !== '' && json_decode((string) $config, true) === null) {
            return $this->fail('config 必须是合法 JSON', 422);
        }

        $provider = new CdnProvider();
        $provider->id       = $this->generateId();
        $provider->name     = $request->input('name');
        $provider->provider = $providerName;
        $provider->config   = $config !== '' ? (string) $config : null;
        $provider->status   = (int) $request->input('status');
        $provider->sort     = (int) $request->input('sort', 0);
        $provider->save();

        return $this->success(['id' => $this->encodeId($provider->id)], '创建成功');
    }

    /**
     * @Apidoc\Title("更新 CDN 厂商")
     * @Apidoc\Desc("更新 CDN 厂商配置，config 留空不修改")
     * @Apidoc\Url("/admin/cdn/provider/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     */
    public function update(Request $request, string $hashid): Response
    {
        $provider = CdnProvider::find($this->decodeId($hashid));
        if (!$provider) {
            return $this->fail('CDN厂商不存在', 404);
        }

        $validator = validator($request->all(), [
            'name'     => 'string|max:50',
            'provider' => 'in:cloudflare,cloudfront,aliyun,tencent,huawei',
            'config'   => 'nullable|string',
            'status'   => 'in:0,1',
            'sort'     => 'integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->has('provider')) {
            $newProvider = (string) $request->input('provider');
            if (CdnProvider::where('provider', $newProvider)->where('id', '!=', $provider->id)->exists()) {
                return $this->fail("厂商 {$newProvider} 已存在", 422);
            }
        }

        foreach (['name', 'provider', 'status', 'sort'] as $field) {
            if ($request->has($field)) {
                $provider->$field = in_array($field, ['status', 'sort'], true)
                    ? (int) $request->input($field)
                    : $request->input($field);
            }
        }
        if ($request->has('config') && $request->input('config') !== '') {
            $config = (string) $request->input('config');
            if (json_decode($config, true) === null) {
                return $this->fail('config 必须是合法 JSON', 422);
            }
            $provider->config = $config;
        }

        $provider->save();

        return $this->success([], '更新成功');
    }

    /**
     * @Apidoc\Title("删除 CDN 厂商")
     * @Apidoc\Desc("删除 CDN 厂商配置")
     * @Apidoc\Url("/admin/cdn/provider/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     */
    public function delete(Request $request, string $hashid): Response
    {
        $provider = CdnProvider::find($this->decodeId($hashid));
        if (!$provider) {
            return $this->fail('CDN厂商不存在', 404);
        }
        $provider->delete();

        return $this->success([], '删除成功');
    }

    /**
     * @Apidoc\Title("CDN 连通测试")
     * @Apidoc\Desc("HeadBucket 验证厂商凭据与 bucket")
     * @Apidoc\Url("/admin/cdn/provider/test")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("id", type="string", require=true, desc="厂商ID(hashid编码)")
     */
    public function test(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $provider = CdnProvider::find($this->decodeId($request->input('id')));
        if (!$provider) {
            return $this->fail('CDN厂商不存在', 404);
        }

        try {
            $config = is_string($provider->config)
                ? (json_decode($provider->config, true) ?: [])
                : (array) $provider->config;
            (new CdnProbeService())->test($provider->provider, $config);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '连通正常');
    }
}
