<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use HuaweiCloud\SDK\Cdn\V1\CdnClient;
use HuaweiCloud\SDK\Cdn\V1\Model\CreatePreheatingTasksRequest;
use HuaweiCloud\SDK\Cdn\V1\Model\CreateRefreshTasksRequest;
use HuaweiCloud\SDK\Cdn\V1\Model\PreheatingTaskRequestBody;
use HuaweiCloud\SDK\Cdn\V1\Model\PreheatingTaskRequest;
use HuaweiCloud\SDK\Cdn\V1\Model\RefreshTaskRequestBody;
use HuaweiCloud\SDK\Cdn\V1\Model\RefreshTaskRequest;
use HuaweiCloud\SDK\Core\Auth\GlobalCredentials;

class HuaweiProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CdnClient $cdn;
    private array $config;

    public function __construct(array $config, ?object $s3 = null, ?object $cdn = null)
    {
        $this->config = $config;
        $region = $config['region'] ?? 'cn-north-4';
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => "https://obs.{$region}.myhuaweicloud.com",
            'credentials' => [
                'key' => $config['ak'] ?? '',
                'secret' => $config['sk'] ?? '',
            ],
        ]);
        $this->cdn = $cdn ?? CdnClient::newBuilder()
            ->withCredentials(new GlobalCredentials($config['ak'] ?? '', $config['sk'] ?? ''))
            ->withEndpoint('https://cdn.myhuaweicloud.com')
            ->build();
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
            throw new CdnException('huawei', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $body = new RefreshTaskRequestBody();
            $body->setType('file');
            $body->setUrls($urls);
            $req = new RefreshTaskRequest();
            $req->setRefreshTask($body);
            $outer = new CreateRefreshTasksRequest();
            $outer->setBody($req);
            $resp = $this->cdn->createRefreshTasks($outer);
            return [$resp->getRefreshTask() ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('huawei', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $body = new PreheatingTaskRequestBody();
            $body->setUrls($urls);
            $req = new PreheatingTaskRequest();
            $req->setPreheatingTask($body);
            $outer = new CreatePreheatingTasksRequest();
            $outer->setBody($req);
            $resp = $this->cdn->createPreheatingTasks($outer);
            return [$resp->getPreheatingTask() ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('huawei', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
