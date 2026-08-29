<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CloudFrontProvider;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

class CloudFrontProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'd111111abcdef8.cloudfront.net',
        'distribution_id' => 'E123',
        's3' => ['region' => 'us-east-1', 'access_key_id' => 'k', 'secret_access_key' => 's'],
    ];

    private function provider(?object $s3 = null, ?CloudFrontClient $cf = null): CloudFrontProvider
    {
        return new CloudFrontProvider($this->config, $s3, $cf);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(['putObject'])->getMock();
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'game/v1/res.zip';
        }));
        $url = $this->provider($s3)->upload('game/v1/res.zip', '/tmp/res.zip');
        $this->assertSame('https://d111111abcdef8.cloudfront.net/game/v1/res.zip', $url);
    }

    public function testPurgeCreatesInvalidation(): void
    {
        $cf = $this->getMockBuilder(CloudFrontClient::class)->disableOriginalConstructor()->addMethods(['createInvalidation'])->getMock();
        $cf->expects($this->once())->method('createInvalidation')->with($this->callback(function (array $args) {
            return $args['DistributionId'] === 'E123'
                && $args['InvalidationBatch']['Paths']['Items'] === ['/avatar/1.jpg'];
        }))->willReturn(['Invalidation' => ['Id' => 'I123']]);
        $this->assertSame(['I123'], $this->provider(cf: $cf)->purge(['https://d111111abcdef8.cloudfront.net/avatar/1.jpg']));
    }

    public function testPreloadReturnsEmpty(): void
    {
        $this->assertSame([], $this->provider()->preload(['https://d111111abcdef8.cloudfront.net/x.jpg']));
    }
}
