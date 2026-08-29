<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class CloudflareProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private ClientInterface $http;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?ClientInterface $http = null)
    {
        $this->config = $config;
        $endpoint = str_replace('{account_id}', $config['account_id'] ?? '', $config['s3']['endpoint'] ?? '');
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $config['s3']['region'] ?? 'auto',
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => $config['s3']['access_key_id'] ?? '',
                'secret' => $config['s3']['secret_access_key'] ?? '',
            ],
        ]);
        $this->http = $http ?? new Client();
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
            throw new CdnException('cloudflare', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $resp = $this->http->post("https://api.cloudflare.com/client/v4/zones/{$this->config['zone_id']}/purge_cache", [
                'headers' => ['Authorization' => "Bearer {$this->config['api_token']}"],
                'json' => ['files' => $urls],
            ]);
            $body = json_decode((string) $resp->getBody(), true);
            if (!($body['success'] ?? false)) {
                throw new CdnException('cloudflare', 'purge', $body['errors'][0]['message'] ?? 'purge failed');
            }
            return [$body['result']['id'] ?? ''];
        } catch (CdnException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CdnException('cloudflare', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        // R2 无预热 API，缓存填充由首次访问完成
        return [];
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
