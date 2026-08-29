<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

return [
    'default' => 'cloudflare',
    'providers' => [
        'cloudflare' => [
            'bucket' => 'static',
            'domain' => 'cdn.example.com',
            'account_id' => env('CF_ACCOUNT_ID', ''),
            'api_token' => env('CF_API_TOKEN', ''),
            'zone_id'   => env('CF_ZONE_ID', ''),
            's3' => [
                'endpoint' => 'https://{account_id}.r2.cloudflarestorage.com',
                'region' => 'auto',
                'access_key_id' => env('CF_R2_ACCESS_KEY', ''),
                'secret_access_key' => env('CF_R2_SECRET_KEY', ''),
            ],
        ],
        'cloudfront' => [
            'bucket' => 'static',
            'domain' => 'd111111abcdef8.cloudfront.net',
            'distribution_id' => env('CF_DISTRIBUTION_ID', ''),
            's3' => [
                'region' => 'us-east-1',
                'access_key_id' => env('AWS_ACCESS_KEY_ID', ''),
                'secret_access_key' => env('AWS_SECRET_ACCESS_KEY', ''),
            ],
        ],
        'aliyun' => [
            'bucket' => 'static',
            'domain' => 'cdn.aliyun.example.com',
            'access_key_id' => env('ALI_AK', ''),
            'access_key_secret' => env('ALI_SK', ''),
            'region' => 'oss-cn-hangzhou',
        ],
        'tencent' => [
            'bucket' => 'static',
            'domain' => 'cdn.tencent.example.com',
            'secret_id'  => env('TENCENT_SECRET_ID', ''),
            'secret_key' => env('TENCENT_SECRET_KEY', ''),
            'region' => 'ap-guangzhou',
        ],
        'huawei' => [
            'bucket' => 'static',
            'domain' => 'cdn.huawei.example.com',
            'ak' => env('HUAWEI_AK', ''),
            'sk' => env('HUAWEI_SK', ''),
            'region' => 'cn-north-4',
        ],
    ],
];
