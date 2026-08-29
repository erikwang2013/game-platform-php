<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use AlibabaCloud\SDK\Cdn\V20180510\Cdn;
use AlibabaCloud\SDK\Cdn\V20180510\Models\RefreshObjectCachesResponse;
use AlibabaCloud\SDK\Cdn\V20180510\Models\RefreshObjectCachesResponseBody;
use AlibabaCloud\SDK\Cdn\V20180510\Models\PushObjectCacheResponse;
use AlibabaCloud\SDK\Cdn\V20180510\Models\PushObjectCacheResponseBody;
use app\cdn\AliyunProvider;
use app\cdn\CdnException;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

class AliyunProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.aliyun.example.com',
        'access_key_id' => 'LTAI-test',
        'access_key_secret' => 'secret',
        'region' => 'oss-cn-hangzhou',
    ];

    private function provider(?object $s3 = null, ?object $cdn = null): AliyunProvider
    {
        return new AliyunProvider($this->config, $s3, $cdn);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(['putObject'])->getMock();
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'avatar/1.jpg';
        }));
        $url = $this->provider($s3)->upload('avatar/1.jpg', '/tmp/x.jpg');
        $this->assertSame('https://cdn.aliyun.example.com/avatar/1.jpg', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $cdn = $this->getMockBuilder(Cdn::class)->disableOriginalConstructor()->onlyMethods(['refreshObjectCaches'])->getMock();
        $cdn->expects($this->once())->method('refreshObjectCaches')->with($this->callback(function (object $req) {
            return $req->objectPath === "https://cdn.aliyun.example.com/avatar/1.jpg\nhttps://cdn.aliyun.example.com/avatar/2.jpg"
                && $req->objectType === 'File';
        }))->willReturn($this->refreshResponse('T123'));
        $this->assertSame(
            ['T123'],
            $this->provider(cdn: $cdn)->purge(['https://cdn.aliyun.example.com/avatar/1.jpg', 'https://cdn.aliyun.example.com/avatar/2.jpg'])
        );
    }

    public function testPreloadReturnsTaskId(): void
    {
        $cdn = $this->getMockBuilder(Cdn::class)->disableOriginalConstructor()->onlyMethods(['pushObjectCache'])->getMock();
        $cdn->expects($this->once())->method('pushObjectCache')->with($this->callback(function (object $req) {
            return $req->objectPath === 'https://cdn.aliyun.example.com/avatar/1.jpg' && $req->objectType === 'File';
        }))->willReturn($this->pushResponse('P456'));
        $this->assertSame(['P456'], $this->provider(cdn: $cdn)->preload(['https://cdn.aliyun.example.com/avatar/1.jpg']));
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->getMockBuilder(Cdn::class)->disableOriginalConstructor()->onlyMethods(['refreshObjectCaches'])->getMock();
        $cdn->method('refreshObjectCaches')->willThrowException(new \RuntimeException('api down'));
        $this->expectException(CdnException::class);
        $this->provider(cdn: $cdn)->purge(['https://cdn.aliyun.example.com/avatar/1.jpg']);
    }

    private function refreshResponse(string $taskId): RefreshObjectCachesResponse
    {
        $body = new RefreshObjectCachesResponseBody();
        $body->refreshTaskId = $taskId;
        $resp = new RefreshObjectCachesResponse();
        $resp->body = $body;
        return $resp;
    }

    private function pushResponse(string $taskId): PushObjectCacheResponse
    {
        $body = new PushObjectCacheResponseBody();
        $body->pushTaskId = $taskId;
        $resp = new PushObjectCacheResponse();
        $resp->body = $body;
        return $resp;
    }
}
