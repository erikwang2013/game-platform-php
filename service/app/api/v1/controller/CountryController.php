<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * C端 - 国家
 *
 * @Apidoc\Title("国家")
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
        return $this->success([
            'list' => [
                ['code' => 'CN', 'name' => 'China'],
                ['code' => 'US', 'name' => 'United States'],
                ['code' => 'JP', 'name' => 'Japan'],
                ['code' => 'KR', 'name' => 'South Korea'],
                ['code' => 'GB', 'name' => 'United Kingdom'],
            ],
        ]);
    }

    /**
     * @Apidoc\Title("国家详情")
     * @Apidoc\Url("/api/country/{code}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"国家代码(ISO 3166-1)")
     */
    public function detail(Request $request, string $code): Response
    {
        $code = strtoupper($code);

        $countries = [
            'CN' => ['code' => 'CN', 'name' => 'China', 'currency' => 'CNY', 'locale' => 'zh-CN'],
            'US' => ['code' => 'US', 'name' => 'United States', 'currency' => 'USD', 'locale' => 'en-US'],
            'JP' => ['code' => 'JP', 'name' => 'Japan', 'currency' => 'JPY', 'locale' => 'ja-JP'],
            'KR' => ['code' => 'KR', 'name' => 'South Korea', 'currency' => 'KRW', 'locale' => 'ko-KR'],
            'GB' => ['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP', 'locale' => 'en-GB'],
        ];

        if (!isset($countries[$code])) {
            return $this->fail('Country not found', 404);
        }

        return $this->success($countries[$code]);
    }
}
