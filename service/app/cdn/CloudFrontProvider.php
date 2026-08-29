<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;

class CloudFrontProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CloudFrontClient $cf;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?CloudFrontClient $cf = null)
    {
        $this->config = $config;
        $credentials = ['key' => $config['s3']['access_key_id'] ?? '', 'secret' => $config['s3']['secret_access_key'] ?? ''];
        $region = $config['s3']['region'] ?? 'us-east-1';
        $this->s3 = $s3 ?? new S3Client(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
        $this->cf = $cf ?? new CloudFrontClient(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
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
            throw new CdnException('cloudfront', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        $paths = array_map(fn (string $u) => parse_url($u, PHP_URL_PATH) ?: '/', $urls);
        try {
            $result = $this->cf->createInvalidation([
                'DistributionId' => $this->config['distribution_id'],
                'InvalidationBatch' => [
                    'Paths' => ['Quantity' => count($paths), 'Items' => $paths],
                    'CallerReference' => (string) time() . '-' . bin2hex(random_bytes(4)),
                ],
            ]);
            return [$result['Invalidation']['Id'] ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('cloudfront', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        // CloudFront 无预热 API
        return [];
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
