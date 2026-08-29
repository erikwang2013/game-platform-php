<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\common\CdnProbeService;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Command;

/**
 * CDN 连通测试服务测试
 */
class CdnProbeServiceTest extends TestCase
{
    private function mockS3(): S3Client
    {
        // headBucket 是 @method 文档块方法，PHPUnit 12 通过 __call 分发 mock
        return $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    #[Test]
    public function testSuccessOnHeadBucket(): void
    {
        $s3 = $this->mockS3();
        $s3->expects($this->once())
           ->method('__call')
           ->with('headBucket', [['Bucket' => 'static']])
           ->willReturn([]);
        (new CdnProbeService($s3))->test('aliyun', [
            'bucket' => 'static',
            'access_key_id' => 'AK',
            'access_key_secret' => 'SK',
            'region' => 'oss-cn-hangzhou',
        ]);
        $this->assertTrue(true);
    }

    #[Test]
    public function testFailureWrapsAwsException(): void
    {
        $s3 = $this->mockS3();
        $s3->expects($this->once())
           ->method('__call')
           ->willThrowException(new AwsException('Access Denied', new Command('HeadBucket')));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('连通测试失败');
        (new CdnProbeService($s3))->test('aliyun', [
            'bucket' => 'static',
            'access_key_id' => 'AK',
            'access_key_secret' => 'SK',
            'region' => 'oss-cn-hangzhou',
        ]);
    }

    #[Test]
    public function testMissingCredentialsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('凭据未配置');
        (new CdnProbeService())->test('aliyun', [
            'bucket' => 'static',
            'region' => 'oss-cn-hangzhou',
        ]);
    }

    #[Test]
    public function testUnknownProviderRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new CdnProbeService())->test('nobody', ['bucket' => 'b']);
    }

    #[Test]
    public function testNestedS3ConfigCloudflare(): void
    {
        $s3 = $this->mockS3();
        $s3->expects($this->once())
           ->method('__call')
           ->with('headBucket', [['Bucket' => 'static']])
           ->willReturn([]);
        (new CdnProbeService($s3))->test('cloudflare', [
            'bucket' => 'static',
            'account_id' => 'abc123',
            's3' => ['region' => 'auto', 'access_key_id' => 'AK', 'secret_access_key' => 'SK'],
        ]);
        $this->assertTrue(true);
    }
}
