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
        $this->cdn = $cdn ?? self::buildCdnClient($config);
    }

    /**
     * 构造华为 CDN 客户端，并回收 SDK 泄漏的错误处理器。
     *
     * SDK 的 UserAgent::GetAppFilePath() 会 set_error_handler 却从不 restore
     * （vendor/huaweicloud/huaweicloud-sdk-php/Core/src/Http/UserAgent.php:192，
     * 经 ClientBuilder::build() -> GetUserAgentMessage() 触发）。而 webman 在
     * vendor/workerman/webman-framework/src/support/bootstrap.php:31 装的处理器
     * 负责把 PHP 错误转成异常——被返回 false 的处理器顶掉后，本 worker 进程后续
     * 所有 PHP 错误都只落 stderr、不再抛异常，是长驻进程里的静默降级。
     *
     * SDK 在 ClientBuilder::__construct(:63) 与 Client::__construct(:52) 各装一次、
     * 都不还原，故一次 build 压两层；按层弹回调用前的处理器为止，用身份比对而非
     * 固定次数——未来 SDK 增删安装点仍收敛，也不会误弹 webman 自己的处理器。
     */
    private static function buildCdnClient(array $config): CdnClient
    {
        $prev = set_error_handler(static fn () => false);
        restore_error_handler();

        try {
            return CdnClient::newBuilder()
                ->withCredentials(new GlobalCredentials($config['ak'] ?? '', $config['sk'] ?? ''))
                ->withEndpoint('https://cdn.myhuaweicloud.com')
                ->build();
        } finally {
            // 先探后弹：$now === $prev 说明已回到调用前状态，此时不能再弹
            for ($i = 0; $i < 8; $i++) {
                $now = set_error_handler(static fn () => false);
                restore_error_handler();
                if ($now === $prev) {
                    break;
                }
                restore_error_handler();
            }
        }
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
