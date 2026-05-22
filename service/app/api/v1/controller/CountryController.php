<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\CountryConfig;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Country")
 * @Apidoc\Group("country")
 */
class CountryController extends BaseController
{
    /**
     * @Apidoc\Title("Country List")
     * @Apidoc\Url("/api/country/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        $countries = CountryConfig::where('status', 1)
            ->orderBy('country_code', 'asc')
            ->get();

        $items = [];
        foreach ($countries as $country) {
            $items[] = [
                'id'              => $this->encodeId($country->id),
                'country_code'    => $country->country_code,
                'country_name'    => $country->country_name,
                'currency_code'   => $country->currency_code,
                'currency_symbol' => $country->currency_symbol,
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * @Apidoc\Title("Country Detail")
     * @Apidoc\Url("/api/country/{code}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"Country code (ISO 3166-1 alpha-2, e.g. US, CN, JP)")
     */
    public function detail(Request $request, string $code): Response
    {
        $code = strtoupper(trim($code));

        $country = CountryConfig::where('country_code', $code)
            ->where('status', 1)
            ->first();

        if (!$country) {
            return $this->fail('Country not found', 404);
        }

        // Parse payment_methods if it's a JSON string
        $paymentMethods = $country->payment_methods;
        if (is_string($paymentMethods)) {
            $paymentMethods = json_decode($paymentMethods, true);
        }

        return $this->success([
            'id'               => $this->encodeId($country->id),
            'country_code'     => $country->country_code,
            'country_name'     => $country->country_name,
            'currency_code'    => $country->currency_code,
            'currency_symbol'  => $country->currency_symbol,
            'language'         => $country->language,
            'timezone'         => $country->timezone,
            'payment_methods'  => is_array($paymentMethods) ? $paymentMethods : [],
        ]);
    }
}
