<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\CountryConfig;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("国家配置")
 * @Apidoc\Group("country")
 */
class CountryController extends BaseController
{
    /**
     * @Apidoc\Title("国家列表")
     * @Apidoc\Url("/api/country/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        $list = CountryConfig::where('status', 1)
            ->orderBy('country_code', 'asc')
            ->get()
            ->map(function ($config) {
                return [
                    'country_code' => $config->country_code,
                    'currency'     => $config->currency,
                    'min_deposit'  => $config->min_deposit,
                ];
            });

        return $this->success(['list' => $list]);
    }

    /**
     * @Apidoc\Title("国家详情")
     * @Apidoc\Url("/api/country/{code}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="code", type="string", require=true, desc="国家代码", in="path")
     */
    public function detail(Request $request, string $code): Response
    {
        $config = CountryConfig::where('country_code', $code)->first();
        if (!$config) {
            return $this->fail('Country not found', 404);
        }

        return $this->success([
            'country_code'     => $config->country_code,
            'currency'         => $config->currency,
            'min_deposit'      => $config->min_deposit,
            'payment_methods'  => json_decode($config->payment_methods ?? '[]', true),
            'withdraw_methods' => json_decode($config->withdraw_methods ?? '[]', true),
            'status'           => $config->status,
        ]);
    }
}
