<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Aws\Exception\AwsException;

/**
 * CDN 连通测试：五家对象存储均兼容 S3 API，用 HeadBucket 验证凭据+bucket
 */
class CdnProbeService
{
    private const ENDPOINTS = [
        'cloudflare' => 'https://{account_id}.r2.cloudflarestorage.com',
        'cloudfront' => 'https://s3.{region}.amazonaws.com',
        'aliyun'     => 'https://s3.{region}.aliyuncs.com',
        'tencent'    => 'https://cos.{region}.myqcloud.com',
        'huawei'     => 'https://obs.{region}.myhuaweicloud.com',
    ];

    public function __construct(private ?S3ClientInterface $s3 = null)
    {
    }

    /** @throws \RuntimeException 凭据/bucket 验证失败 */
    public function test(string $provider, array $config): void
    {
        if (!isset(self::ENDPOINTS[$provider])) {
            throw new \RuntimeException("不支持的CDN厂商: {$provider}");
        }
        $endpoint = str_replace(
            ['{account_id}', '{region}'],
            [$config['account_id'] ?? '', $config['s3']['region'] ?? $config['region'] ?? ''],
            self::ENDPOINTS[$provider]
        );
        // cloudflare/cloudfront 配置嵌套在 s3 block，其余扁平
        $s3cfg = $config['s3'] ?? $config;
        $key    = $s3cfg['access_key_id'] ?? $s3cfg['secret_id'] ?? $s3cfg['ak'] ?? '';
        $secret = $s3cfg['secret_access_key'] ?? $s3cfg['access_key_secret'] ?? $s3cfg['secret_key'] ?? $s3cfg['sk'] ?? '';
        if ($key === '' || $secret === '') {
            throw new \RuntimeException("厂商 {$provider} 凭据未配置");
        }
        try {
            $client = $this->s3 ?? new S3Client([
                'version'     => 'latest',
                'region'      => $config['s3']['region'] ?? $config['region'] ?? 'us-east-1',
                'endpoint'    => $endpoint,
                'credentials' => ['key' => $key, 'secret' => $secret],
            ]);
            $client->headBucket(['Bucket' => $config['bucket'] ?? '']);
        } catch (AwsException $e) {
            $detail = $e->getAwsErrorMessage() ?: $e->getMessage();
            throw new \RuntimeException("连通测试失败: {$detail}");
        }
    }
}
