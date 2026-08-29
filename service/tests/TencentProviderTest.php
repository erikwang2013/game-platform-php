<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\TencentProvider;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use TencentCloud\Cdn\V20180606\CdnClient;
use TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheResponse;
use TencentCloud\Cdn\V20180606\Models\PushUrlsCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PushUrlsCacheResponse;

class TencentProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.tencent.example.com',
        'secret_id' => 'AKID-test',
        'secret_key' => 'sk',
        'region' => 'ap-guangzhou',
    ];

    private function provider(?object $s3 = null, ?object $cdn = null): TencentProvider
    {
        return new TencentProvider($this->config, $s3, $cdn);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(['putObject'])->getMock();
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'chat/1.png';
        }));
        $url = $this->provider($s3)->upload('chat/1.png', '/tmp/1.png');
        $this->assertSame('https://cdn.tencent.example.com/chat/1.png', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $cdn = $this->getMockBuilder(CdnClient::class)->disableOriginalConstructor()->addMethods(['PurgeUrlsCache'])->getMock();
        $cdn->expects($this->once())->method('PurgeUrlsCache')->with($this->callback(function (PurgeUrlsCacheRequest $req) {
            return $req->Urls === ['https://cdn.tencent.example.com/chat/1.png', 'https://cdn.tencent.example.com/chat/2.png'];
        }))->willReturn($this->purgeResponse('T123'));
        $this->assertSame(
            ['T123'],
            $this->provider(cdn: $cdn)->purge(['https://cdn.tencent.example.com/chat/1.png', 'https://cdn.tencent.example.com/chat/2.png'])
        );
    }

    public function testPreloadReturnsTaskId(): void
    {
        $cdn = $this->getMockBuilder(CdnClient::class)->disableOriginalConstructor()->addMethods(['PushUrlsCache'])->getMock();
        $cdn->expects($this->once())->method('PushUrlsCache')->with($this->callback(function (PushUrlsCacheRequest $req) {
            return $req->Urls === ['https://cdn.tencent.example.com/chat/1.png'];
        }))->willReturn($this->pushResponse('T456'));
        $this->assertSame(
            ['T456'],
            $this->provider(cdn: $cdn)->preload(['https://cdn.tencent.example.com/chat/1.png'])
        );
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->getMockBuilder(CdnClient::class)->disableOriginalConstructor()->addMethods(['PurgeUrlsCache'])->getMock();
        $cdn->expects($this->once())->method('PurgeUrlsCache')->willThrowException(new \RuntimeException('boom'));
        $this->expectException(CdnException::class);
        $this->expectExceptionMessage('[tencent:purge] boom');
        $this->provider(cdn: $cdn)->purge(['https://cdn.tencent.example.com/chat/1.png']);
    }

    public function testPreloadFailureThrows(): void
    {
        $cdn = $this->getMockBuilder(CdnClient::class)->disableOriginalConstructor()->addMethods(['PushUrlsCache'])->getMock();
        $cdn->expects($this->once())->method('PushUrlsCache')->willThrowException(new \RuntimeException('boom'));
        $this->expectException(CdnException::class);
        $this->expectExceptionMessage('[tencent:preload] boom');
        $this->provider(cdn: $cdn)->preload(['https://cdn.tencent.example.com/chat/1.png']);
    }

    private function purgeResponse(string $taskId): PurgeUrlsCacheResponse
    {
        $resp = new PurgeUrlsCacheResponse();
        $resp->TaskId = $taskId;
        return $resp;
    }

    private function pushResponse(string $taskId): PushUrlsCacheResponse
    {
        $resp = new PushUrlsCacheResponse();
        $resp->TaskId = $taskId;
        return $resp;
    }
}
