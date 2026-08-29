<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\HuaweiProvider;
use Aws\S3\S3Client;
use HuaweiCloud\SDK\Cdn\V1\CdnClient;
use HuaweiCloud\SDK\Cdn\V1\Model\CreateRefreshTasksResponse;
use HuaweiCloud\SDK\Cdn\V1\Model\CreatePreheatingTasksResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HuaweiProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.huawei.example.com',
        'ak' => 'ak-test',
        'sk' => 'sk',
        'region' => 'cn-north-4',
    ];

    private function provider(?object $s3 = null, ?object $cdn = null): HuaweiProvider
    {
        return new HuaweiProvider($this->config, $s3, $cdn);
    }

    private function mockCdn(): CdnClient
    {
        return $this->getMockBuilder(CdnClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createRefreshTasks', 'createPreheatingTasks'])
            ->getMock();
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(['putObject'])->getMock();
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'game/v1/res.zip';
        }));
        $url = $this->provider($s3, $this->mockCdn())->upload('game/v1/res.zip', '/tmp/res.zip');
        $this->assertSame('https://cdn.huawei.example.com/game/v1/res.zip', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $resp = (new CreateRefreshTasksResponse())->setRefreshTask('task-123');
        $cdn = $this->mockCdn();
        $cdn->expects($this->once())->method('createRefreshTasks')
            ->with($this->callback(function ($req) {
                return $req->getBody() !== null
                    && $req->getBody()->getRefreshTask()->getType() === 'file'
                    && $req->getBody()->getRefreshTask()->getUrls() === ['https://cdn.huawei.example.com/a.zip'];
            }))
            ->willReturn($resp);
        $this->assertSame(['task-123'], $this->provider(null, $cdn)->purge(['https://cdn.huawei.example.com/a.zip']));
    }

    public function testPreloadReturnsTaskId(): void
    {
        $resp = (new CreatePreheatingTasksResponse())->setPreheatingTask('pre-456');
        $cdn = $this->mockCdn();
        $cdn->expects($this->once())->method('createPreheatingTasks')
            ->with($this->callback(function ($req) {
                return $req->getBody() !== null
                    && $req->getBody()->getPreheatingTask()->getUrls() === ['https://cdn.huawei.example.com/a.zip'];
            }))
            ->willReturn($resp);
        $this->assertSame(['pre-456'], $this->provider(null, $cdn)->preload(['https://cdn.huawei.example.com/a.zip']));
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->mockCdn();
        $cdn->method('createRefreshTasks')->willThrowException(new RuntimeException('boom'));
        $this->expectException(CdnException::class);
        $this->provider(null, $cdn)->purge(['https://cdn.huawei.example.com/a.zip']);
    }
}
