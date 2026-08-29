<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use TencentCloud\Cdn\V20180606\CdnClient;
use TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PushUrlsCacheRequest;
use TencentCloud\Common\Credential;

class TencentProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CdnClient $cdn;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?object $cdn = null)
    {
        $this->config = $config;
        $region = $config['region'] ?? 'ap-guangzhou';
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => "https://cos.{$region}.myqcloud.com",
            'credentials' => [
                'key' => $config['secret_id'] ?? '',
                'secret' => $config['secret_key'] ?? '',
            ],
        ]);
        $this->cdn = $cdn ?? new CdnClient(
            new Credential($config['secret_id'] ?? '', $config['secret_key'] ?? ''),
            $region
        );
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
            throw new CdnException('tencent', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $req = new PurgeUrlsCacheRequest();
            $req->setUrls($urls);
            $resp = $this->cdn->PurgeUrlsCache($req);
            return [$resp->TaskId ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('tencent', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $req = new PushUrlsCacheRequest();
            $req->setUrls($urls);
            $resp = $this->cdn->PushUrlsCache($req);
            return [$resp->TaskId ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('tencent', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
