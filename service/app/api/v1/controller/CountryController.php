<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\CountryConfig;
use support\Request;
use support\Response;

class CountryController extends BaseController
{
    /**
     * 国家配置列表
     * GET /api/country/list
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
     * 国家配置详情
     * GET /api/country/{code}
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
