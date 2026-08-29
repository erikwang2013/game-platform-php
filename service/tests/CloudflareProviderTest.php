<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\CloudflareProvider;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CloudflareProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.example.com',
        'account_id' => 'acc123',
        'api_token' => 'tok',
        'zone_id' => 'zone1',
        's3' => [
            'endpoint' => 'https://{account_id}.r2.cloudflarestorage.com',
            'region' => 'auto',
            'access_key_id' => 'k',
            'secret_access_key' => 's',
        ],
    ];

    private function provider(?S3ClientInterface $s3 = null, ?ClientInterface $http = null): CloudflareProvider
    {
        return new CloudflareProvider($this->config, $s3, $http);
    }

    public function testUploadReturnsUrl(): void
    {
        // putObject 在 S3Client 上仅是以 @method 注释声明的魔术方法（经 AwsClient::__call 分发），
        // 需 addMethods 才能被 PHPUnit 模拟（PHPUnit 11 中为 mock 非存在方法的专用 API）
        $s3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(['putObject'])->getMock();
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'avatar/1.jpg' && $args['SourceFile'] === '/tmp/x.jpg';
        }));
        $url = $this->provider($s3)->upload('avatar/1.jpg', '/tmp/x.jpg');
        $this->assertSame('https://cdn.example.com/avatar/1.jpg', $url);
    }

    public function testPurgeCallsCloudflareApi(): void
    {
        // post 不在 ClientInterface 上（GuzzleHttp\Client 经 ClientTrait 提供），模拟具体 Client 类
        $http = $this->createMock(Client::class);
        $http->expects($this->once())->method('post')
            ->with('https://api.cloudflare.com/client/v4/zones/zone1/purge_cache', $this->callback(function (array $opts) {
                return $opts['headers']['Authorization'] === 'Bearer tok'
                    && $opts['json'] === ['files' => ['https://cdn.example.com/avatar/1.jpg']];
            }))
            ->willReturn(new Response(200, [], '{"success":true,"result":{"id":"purge1"}}'));
        $this->assertSame(['purge1'], $this->provider(http: $http)->purge(['https://cdn.example.com/avatar/1.jpg']));
    }

    public function testPurgeFailureThrows(): void
    {
        $http = $this->createMock(Client::class);
        $http->method('post')->willReturn(new Response(200, [], '{"success":false,"errors":[{"message":"bad zone"}]}'));
        $this->expectException(CdnException::class);
        $this->expectExceptionMessage('bad zone');
        $this->provider(http: $http)->purge(['https://cdn.example.com/avatar/1.jpg']);
    }

    public function testPreloadReturnsEmpty(): void
    {
        $this->assertSame([], $this->provider()->preload(['https://cdn.example.com/avatar/1.jpg']));
    }

    public function testUrlGeneration(): void
    {
        $this->assertSame('https://cdn.example.com/game/v1/res.zip', $this->provider()->url('game/v1/res.zip'));
    }
}
