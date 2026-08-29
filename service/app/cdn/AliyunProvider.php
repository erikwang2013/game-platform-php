<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use AlibabaCloud\SDK\Cdn\V20180510\Cdn;
use AlibabaCloud\SDK\Cdn\V20180510\Models\PushObjectCacheRequest;
use AlibabaCloud\SDK\Cdn\V20180510\Models\RefreshObjectCachesRequest;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Darabonba\OpenApi\Models\Config;

class AliyunProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private Cdn $cdn;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?object $cdn = null)
    {
        $this->config = $config;
        $region = $config['region'] ?? 'oss-cn-hangzhou';
        $credentials = ['key' => $config['access_key_id'] ?? '', 'secret' => $config['access_key_secret'] ?? ''];
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => "https://s3.{$region}.aliyuncs.com",
            'credentials' => $credentials,
        ]);
        $this->cdn = $cdn ?? new Cdn(new Config([
            'accessKeyId' => $config['access_key_id'] ?? '',
            'accessKeySecret' => $config['access_key_secret'] ?? '',
            'regionId' => $config['region'] ?? 'cn-hangzhou',
        ]));
    }

    public function upload(string $key, string $localPath, array $options = []): string
    {
        try {
            $this->s3->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $key,
                'SourceFile' => $localPath,
            ] + $options);
        } catch (\Throwable $e) {
            throw new CdnException('aliyun', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $req = new RefreshObjectCachesRequest([
                'objectPath' => implode("\n", $urls),
                'objectType' => 'File',
            ]);
            $resp = $this->cdn->refreshObjectCaches($req);
            return [$resp->body->refreshTaskId ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('aliyun', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $req = new PushObjectCacheRequest([
                'objectPath' => implode("\n", $urls),
                'objectType' => 'File',
            ]);
            $resp = $this->cdn->pushObjectCache($req);
            return [$resp->body->pushTaskId ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('aliyun', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
