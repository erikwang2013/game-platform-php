# CDN 支持实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 Webman 平台新增统一服务端 CDN 组件，五家厂商（Cloudflare/AWS/阿里云/腾讯云/华为云）各一个适配器，支持上传、缓存刷新、预热、URL 生成。

**Architecture:** 镜像 `app/payment/` 多厂商模式（GatewayFactory + 接口 + 每厂商一个类）。存储层五家全部走 aws-sdk-php 的 S3 client（R2/COS/OBS/OSS-S3 兼容端点），缓存管理 API 各家用自己的 SDK/HTTP。业务代码经 `CdnFactory::resolve()` 获取 provider，不直接 new。

**Tech Stack:** PHP 8.1+, Webman, aws/aws-sdk-php, alibabacloud/cdn-20180510, tencentcloud/tencentcloud-sdk-php, huaweicloud/huaweicloud-sdk-php, Guzzle（已装）, PHPUnit 11

**参考 spec:** `docs/superpowers/specs/2026-08-29-cdn-support-design.md`

**工作目录:** `/home/wwwroot/game-platform-php/service`（所有命令在此目录执行）

---

### Task 1: 安装 SDK 依赖

**Files:**
- Modify: `composer.json`（composer require 自动更新）

- [ ] **Step 1: 安装四个 SDK**

```bash
cd /home/wwwroot/game-platform-php/service
composer require aws/aws-sdk-php alibabacloud/cdn-20180510 tencentcloud/tencentcloud-sdk-php huaweicloud/huaweicloud-sdk-php
```

Expected: 安装成功，`composer.json` require 增加四项。华为/腾讯 SDK 较大（各 ~100MB 源码），耗时 1-3 分钟属正常。若某个包名不存在（Packagist 变更），运行 `composer search <厂商> cdn` 查实际包名后重试。

- [ ] **Step 2: 验证 autoload**

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('Aws\\S3\\S3Client') ? 'aws ok' : 'aws missing'; echo PHP_EOL;"
```

Expected: 输出 `aws ok`（其余 SDK 类名在各自 Task 的测试中验证）。

---

### Task 2: CdnException + 接口 + 配置

**Files:**
- Create: `app/cdn/CdnException.php`
- Create: `app/cdn/CdnProviderInterface.php`
- Create: `config/cdn.php`

- [ ] **Step 1: 写 CdnException**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

class CdnException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $operation,
        string $message
    ) {
        parent::__construct("[{$provider}:{$operation}] {$message}");
    }
}
```

- [ ] **Step 2: 写 CdnProviderInterface**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

interface CdnProviderInterface
{
    /** 上传本地文件到对象存储，返回 CDN 访问 URL */
    public function upload(string $key, string $localPath, array $options = []): string;

    /** 缓存刷新（purge），返回任务 ID 列表（无任务 ID 的厂商返回空数组） */
    public function purge(array $urls): array;

    /** 资源预热（preload），返回任务 ID 列表（无预热 API 的厂商返回空数组） */
    public function preload(array $urls): array;

    /** 按 key 生成 CDN URL（不上传，仅拼接） */
    public function url(string $key): string;
}
```

- [ ] **Step 3: 写 config/cdn.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

return [
    'default' => 'cloudflare',
    'providers' => [
        'cloudflare' => [
            'bucket' => 'static',
            'domain' => 'cdn.example.com',
            'account_id' => env('CF_ACCOUNT_ID', ''),
            'api_token' => env('CF_API_TOKEN', ''),
            'zone_id'   => env('CF_ZONE_ID', ''),
            's3' => [
                'endpoint' => 'https://{account_id}.r2.cloudflarestorage.com',
                'region' => 'auto',
                'access_key_id' => env('CF_R2_ACCESS_KEY', ''),
                'secret_access_key' => env('CF_R2_SECRET_KEY', ''),
            ],
        ],
        'cloudfront' => [
            'bucket' => 'static',
            'domain' => 'd111111abcdef8.cloudfront.net',
            'distribution_id' => env('CF_DISTRIBUTION_ID', ''),
            's3' => [
                'region' => 'us-east-1',
                'access_key_id' => env('AWS_ACCESS_KEY_ID', ''),
                'secret_access_key' => env('AWS_SECRET_ACCESS_KEY', ''),
            ],
        ],
        'aliyun' => [
            'bucket' => 'static',
            'domain' => 'cdn.aliyun.example.com',
            'access_key_id' => env('ALI_AK', ''),
            'access_key_secret' => env('ALI_SK', ''),
            'region' => 'oss-cn-hangzhou',
        ],
        'tencent' => [
            'bucket' => 'static',
            'domain' => 'cdn.tencent.example.com',
            'secret_id'  => env('TENCENT_SECRET_ID', ''),
            'secret_key' => env('TENCENT_SECRET_KEY', ''),
            'region' => 'ap-guangzhou',
        ],
        'huawei' => [
            'bucket' => 'static',
            'domain' => 'cdn.huawei.example.com',
            'ak' => env('HUAWEI_AK', ''),
            'sk' => env('HUAWEI_SK', ''),
            'region' => 'cn-north-4',
        ],
    ],
];
```

- [ ] **Step 4: 验证语法**

```bash
php -l app/cdn/CdnException.php && php -l app/cdn/CdnProviderInterface.php && php -l config/cdn.php
```

Expected: 三行均输出 `No syntax errors detected`。

- [ ] **Step 5: Commit**

```bash
git add app/cdn/CdnException.php app/cdn/CdnProviderInterface.php config/cdn.php
git commit -m "feat(cdn): CdnException + CdnProviderInterface + 五厂商配置骨架"
```

---

### Task 3: CdnFactory

**Files:**
- Create: `app/cdn/CdnFactory.php`
- Test: `tests/CdnFactoryTest.php`

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnFactory;
use app\cdn\CdnProviderInterface;
use PHPUnit\Framework\TestCase;

class CdnFactoryTest extends TestCase
{
    public function testResolveAllProviders(): void
    {
        $config = require __DIR__ . '/../config/cdn.php';
        foreach (array_keys($config['providers']) as $provider) {
            $resolved = CdnFactory::resolve($provider, $config);
            $this->assertInstanceOf(CdnProviderInterface::class, $resolved, "provider {$provider}");
        }
    }

    public function testUnknownProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CdnFactory::resolve('akamai', require __DIR__ . '/../config/cdn.php');
    }
}
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/CdnFactoryTest.php --filter testUnknownProviderThrows`
Expected: FAIL — `Class "app\cdn\CdnFactory" not found`

- [ ] **Step 3: 实现 CdnFactory**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

class CdnFactory
{
    /**
     * @param array{default: string, providers: array<string, array>} $config
     */
    public static function resolve(string $provider, array $config): CdnProviderInterface
    {
        $providers = $config['providers'] ?? [];
        if (!isset($providers[$provider])) {
            throw new \InvalidArgumentException("Unsupported CDN provider: {$provider}");
        }
        return match ($provider) {
            'cloudflare' => new CloudflareProvider($providers['cloudflare']),
            'cloudfront' => new CloudFrontProvider($providers['cloudfront']),
            'aliyun'     => new AliyunProvider($providers['aliyun']),
            'tencent'    => new TencentProvider($providers['tencent']),
            'huawei'     => new HuaweiProvider($providers['huawei']),
        };
    }
}
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/CdnFactoryTest.php`
Expected: 2 tests PASS（5 个 provider 全部 resolve 成功 + 未知抛异常）

- [ ] **Step 5: Commit**

```bash
git add app/cdn/CdnFactory.php tests/CdnFactoryTest.php
git commit -m "feat(cdn): CdnFactory 解析五厂商适配器"
```

---

### Task 4: CloudflareProvider（R2 存储 + purge_cache HTTP）

**Files:**
- Create: `app/cdn/CloudflareProvider.php`
- Test: `tests/CloudflareProviderTest.php`

**要点:** R2 走 S3 client（endpoint 含 `{account_id}` 占位符需替换）；purge 用 Guzzle 直调 Cloudflare API；R2 无预热 API，preload 返回空数组。

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\CloudflareProvider;
use Aws\S3\S3ClientInterface;
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
        $s3 = $this->createMock(S3ClientInterface::class);
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'avatar/1.jpg' && $args['SourceFile'] === '/tmp/x.jpg';
        }));
        $url = $this->provider($s3)->upload('avatar/1.jpg', '/tmp/x.jpg');
        $this->assertSame('https://cdn.example.com/avatar/1.jpg', $url);
    }

    public function testPurgeCallsCloudflareApi(): void
    {
        $http = $this->createMock(ClientInterface::class);
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
        $http = $this->createMock(ClientInterface::class);
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
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/CloudflareProviderTest.php`
Expected: FAIL — `Class "app\cdn\CloudflareProvider" not found`

- [ ] **Step 3: 实现 CloudflareProvider**

```php
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
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/CloudflareProviderTest.php`
Expected: 5 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/cdn/CloudflareProvider.php tests/CloudflareProviderTest.php
git commit -m "feat(cdn): CloudflareProvider（R2 上传 + purge_cache API）"
```

---

### Task 5: CloudFrontProvider（S3 存储 + invalidation）

**Files:**
- Create: `app/cdn/CloudFrontProvider.php`
- Test: `tests/CloudFrontProviderTest.php`

**要点:** S3 原生；purge = CloudFront CreateInvalidation（aws-sdk-php 自带 CloudFrontClient）；CloudFront 无预热 API，preload 返回空数组。

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CloudFrontProvider;
use Aws\CloudFront\CloudFrontClientInterface;
use Aws\S3\S3ClientInterface;
use PHPUnit\Framework\TestCase;

class CloudFrontProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'd111111abcdef8.cloudfront.net',
        'distribution_id' => 'E123',
        's3' => ['region' => 'us-east-1', 'access_key_id' => 'k', 'secret_access_key' => 's'],
    ];

    private function provider(?S3ClientInterface $s3 = null, ?CloudFrontClientInterface $cf = null): CloudFrontProvider
    {
        return new CloudFrontProvider($this->config, $s3, $cf);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->createMock(S3ClientInterface::class);
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'game/v1/res.zip';
        }));
        $url = $this->provider($s3)->upload('game/v1/res.zip', '/tmp/res.zip');
        $this->assertSame('https://d111111abcdef8.cloudfront.net/game/v1/res.zip', $url);
    }

    public function testPurgeCreatesInvalidation(): void
    {
        $cf = $this->createMock(CloudFrontClientInterface::class);
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
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/CloudFrontProviderTest.php`
Expected: FAIL — `Class "app\cdn\CloudFrontProvider" not found`

- [ ] **Step 3: 实现 CloudFrontProvider**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\CloudFrontClientInterface;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;

class CloudFrontProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CloudFrontClientInterface $cf;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?CloudFrontClientInterface $cf = null)
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
        $paths = array_map(fn (string $u) => '/' . parse_url($u, PHP_URL_PATH), $urls);
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
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/CloudFrontProviderTest.php`
Expected: 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/cdn/CloudFrontProvider.php tests/CloudFrontProviderTest.php
git commit -m "feat(cdn): CloudFrontProvider（S3 上传 + invalidation）"
```

---

### Task 6: AliyunProvider（OSS 上传 + RefreshObjectCaches）

**Files:**
- Create: `app/cdn/AliyunProvider.php`
- Test: `tests/AliyunProviderTest.php`

**要点:** OSS 走 S3 client（endpoint `https://s3.{region}.aliyuncs.com`，阿里 S3 兼容端点）；purge = CDN `RefreshObjectCaches`，preload = `PushObjectCache`（SDK: `AlibabaCloud\Cdn\V20180510`）。

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\AliyunProvider;
use Aws\S3\S3ClientInterface;
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

    private function provider(?S3ClientInterface $s3 = null, ?object $cdn = null): AliyunProvider
    {
        return new AliyunProvider($this->config, $s3, $cdn);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->createMock(S3ClientInterface::class);
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'avatar/1.jpg';
        }));
        $url = $this->provider($s3)->upload('avatar/1.jpg', '/tmp/x.jpg');
        $this->assertSame('https://cdn.aliyun.example.com/avatar/1.jpg', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $cdn = $this->createMock(\AlibabaCloud\Cdn\V20180510\Cdn::class);
        $cdn->expects($this->once())->method('refreshObjectCaches')->willReturn([
            'RefreshTaskId' => 'task-1',
        ]);
        $this->assertSame(['task-1'], $this->provider(cdn: $cdn)->purge(['https://cdn.aliyun.example.com/avatar/1.jpg']));
    }

    public function testPreloadReturnsTaskId(): void
    {
        $cdn = $this->createMock(\AlibabaCloud\Cdn\V20180510\Cdn::class);
        $cdn->expects($this->once())->method('pushObjectCache')->willReturn([
            'PushTaskId' => 'push-1',
        ]);
        $this->assertSame(['push-1'], $this->provider(cdn: $cdn)->preload(['https://cdn.aliyun.example.com/avatar/1.jpg']));
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->createMock(\AlibabaCloud\Cdn\V20180510\Cdn::class);
        $cdn->method('refreshObjectCaches')->willThrowException(new \RuntimeException('no permission'));
        $this->expectException(\app\cdn\CdnException::class);
        $this->provider(cdn: $cdn)->purge(['https://cdn.aliyun.example.com/x.jpg']);
    }
}
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/AliyunProviderTest.php`
Expected: FAIL — `Class "app\cdn\AliyunProvider" not found`

- [ ] **Step 3: 实现 AliyunProvider**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use AlibabaCloud\Cdn\V20180510\Cdn;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;

class AliyunProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private Cdn $cdn;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?Cdn $cdn = null)
    {
        $this->config = $config;
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'oss-cn-hangzhou',
            'endpoint' => 'https://s3.' . ($config['region'] ?? 'oss-cn-hangzhou') . '.aliyuncs.com',
            'credentials' => [
                'key' => $config['access_key_id'] ?? '',
                'secret' => $config['access_key_secret'] ?? '',
            ],
        ]);
        $this->cdn = $cdn ?? Cdn::create()
            ->withAccessKeyId($config['access_key_id'] ?? '')
            ->withAccessKeySecret($config['access_key_secret'] ?? '')
            ->withRegionId(preg_replace('/^oss-/', '', $config['region'] ?? 'cn-hangzhou'));
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
            throw new CdnException('aliyun', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $result = $this->cdn->refreshObjectCaches([
                'ObjectPath' => implode("\n", $urls),
                'ObjectType' => 'File',
            ]);
            return [$result['RefreshTaskId'] ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('aliyun', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $result = $this->cdn->pushObjectCache([
                'ObjectPath' => implode("\n", $urls),
                'ObjectType' => 'File',
            ]);
            return [$result['PushTaskId'] ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('aliyun', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/AliyunProviderTest.php`
Expected: 4 tests PASS

> 若 alibabacloud/cdn-20180510 的客户端方法签名与上述不同（SDK 是 fluent builder 风格，方法名如 `refreshObjectCaches`），以实际 SDK 为准调整实现与测试——测试 mock 的是客户端接口，改动只在实现层。

- [ ] **Step 5: Commit**

```bash
git add app/cdn/AliyunProvider.php tests/AliyunProviderTest.php
git commit -m "feat(cdn): AliyunProvider（OSS 上传 + 刷新/预热 API）"
```

---

### Task 7: TencentProvider（COS 上传 + PurgeUrlsCache）

**Files:**
- Create: `app/cdn/TencentProvider.php`
- Test: `tests/TencentProviderTest.php`

**要点:** COS 走 S3 client（endpoint `https://cos.{region}.myqcloud.com`，SecretId 即 S3 access key）；purge = `PurgeUrlsCache`，preload = `PushUrlsCache`（SDK: `TencentCloud\Cdn\V20180606`）。

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\TencentProvider;
use Aws\S3\S3ClientInterface;
use PHPUnit\Framework\TestCase;
use TencentCloud\Cdn\V20180606\CdnClient;

class TencentProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.tencent.example.com',
        'secret_id' => 'AKID-test',
        'secret_key' => 'sk',
        'region' => 'ap-guangzhou',
    ];

    private function provider(?S3ClientInterface $s3 = null, ?CdnClient $cdn = null): TencentProvider
    {
        return new TencentProvider($this->config, $s3, $cdn);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->createMock(S3ClientInterface::class);
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'chat/1.png';
        }));
        $url = $this->provider($s3)->upload('chat/1.png', '/tmp/1.png');
        $this->assertSame('https://cdn.tencent.example.com/chat/1.png', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $cdn = $this->createMock(CdnClient::class);
        $resp = new \TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheResponse();
        $resp->deserialize(['TaskId' => 'task-1']);
        $cdn->expects($this->once())->method('PurgeUrlsCache')->willReturn($resp);
        $this->assertSame(['task-1'], $this->provider(cdn: $cdn)->purge(['https://cdn.tencent.example.com/chat/1.png']));
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->createMock(CdnClient::class);
        $cdn->method('PurgeUrlsCache')->willThrowException(new \RuntimeException('auth fail'));
        $this->expectException(CdnException::class);
        $this->provider(cdn: $cdn)->purge(['https://cdn.tencent.example.com/x.png']);
    }
}
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/TencentProviderTest.php`
Expected: FAIL — `Class "app\cdn\TencentProvider" not found`

- [ ] **Step 3: 实现 TencentProvider**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use TencentCloud\Cdn\V20180606\CdnClient;
use TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PushUrlsCacheRequest;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;

class TencentProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CdnClient $cdn;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?CdnClient $cdn = null)
    {
        $this->config = $config;
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'ap-guangzhou',
            'endpoint' => 'https://cos.' . ($config['region'] ?? 'ap-guangzhou') . '.myqcloud.com',
            'credentials' => [
                'key' => $config['secret_id'] ?? '',
                'secret' => $config['secret_key'] ?? '',
            ],
        ]);
        $this->cdn = $cdn ?? new CdnClient(
            new Credential($config['secret_id'] ?? '', $config['secret_key'] ?? ''),
            $config['region'] ?? 'ap-guangzhou',
            new ClientProfile()
        );
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
            throw new CdnException('tencent', 'upload', $e->getMessage());
        }
        return $this->url($key);
    }

    public function purge(array $urls): array
    {
        try {
            $req = new PurgeUrlsCacheRequest();
            $req->setUrls($urls);
            $resp = $this->cdn->PurgeUrlsCache($req);
            return [$resp->getTaskId() ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('tencent', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $req = new PushUrlsCacheRequest();
            $req->setUrls($urls);
            $resp = $this->cdn->PushUrlsCache($req);
            return [$resp->getTaskId() ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('tencent', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/TencentProviderTest.php`
Expected: 3 tests PASS

> 若 SDK 响应对象取 ID 的方法名不同（如 `getTaskId` 不存在），用 `$resp->toJsonString()` 或响应数组键（`TaskId`）取用。

- [ ] **Step 5: Commit**

```bash
git add app/cdn/TencentProvider.php tests/TencentProviderTest.php
git commit -m "feat(cdn): TencentProvider（COS 上传 + 刷新/预热 API）"
```

---

### Task 8: HuaweiProvider（OBS 上传 + RefreshTask）

**Files:**
- Create: `app/cdn/HuaweiProvider.php`
- Test: `tests/HuaweiProviderTest.php`

**要点:** OBS 走 S3 client（endpoint `https://obs.{region}.myhuaweicloud.com`）；purge = `RefreshTask`，preload = `PreheatingTask`（SDK: `HuaweiCloud\SDK\Cdn\V1`）。

- [ ] **Step 1: 写失败测试**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnException;
use app\cdn\HuaweiProvider;
use Aws\S3\S3ClientInterface;
use HuaweiCloud\SDK\Cdn\V1\CdnClient;
use PHPUnit\Framework\TestCase;

class HuaweiProviderTest extends TestCase
{
    private array $config = [
        'bucket' => 'static',
        'domain' => 'cdn.huawei.example.com',
        'ak' => 'ak-test',
        'sk' => 'sk',
        'region' => 'cn-north-4',
    ];

    private function provider(?S3ClientInterface $s3 = null, ?CdnClient $cdn = null): HuaweiProvider
    {
        return new HuaweiProvider($this->config, $s3, $cdn);
    }

    public function testUploadReturnsUrl(): void
    {
        $s3 = $this->createMock(S3ClientInterface::class);
        $s3->expects($this->once())->method('putObject')->with($this->callback(function (array $args) {
            return $args['Bucket'] === 'static' && $args['Key'] === 'game/v1/res.zip';
        }));
        $url = $this->provider($s3)->upload('game/v1/res.zip', '/tmp/res.zip');
        $this->assertSame('https://cdn.huawei.example.com/game/v1/res.zip', $url);
    }

    public function testPurgeReturnsTaskId(): void
    {
        $cdn = $this->createMock(CdnClient::class);
        $resp = new \HuaweiCloud\SDK\Cdn\V1\Model\RefreshTaskResponse();
        $resp->setRefreshTask(['id' => 'task-1']);
        $cdn->expects($this->once())->method('refreshTask')->willReturn($resp);
        $this->assertSame(['task-1'], $this->provider(cdn: $cdn)->purge(['https://cdn.huawei.example.com/x.zip']));
    }

    public function testPurgeFailureThrows(): void
    {
        $cdn = $this->createMock(CdnClient::class);
        $cdn->method('refreshTask')->willThrowException(new \RuntimeException('no auth'));
        $this->expectException(CdnException::class);
        $this->provider(cdn: $cdn)->purge(['https://cdn.huawei.example.com/x.zip']);
    }
}
```

- [ ] **Step 2: 运行测试验证失败**

Run: `vendor/bin/phpunit tests/HuaweiProviderTest.php`
Expected: FAIL — `Class "app\cdn\HuaweiProvider" not found`

- [ ] **Step 3: 实现 HuaweiProvider**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use HuaweiCloud\SDK\Cdn\V1\CdnClient;
use HuaweiCloud\SDK\Cdn\V1\Model\PreheatingTaskRequest;
use HuaweiCloud\SDK\Cdn\V1\Model\PreheatingTaskRequestBody;
use HuaweiCloud\SDK\Cdn\V1\Model\RefreshTaskRequest;
use HuaweiCloud\SDK\Cdn\V1\Model\RefreshTaskRequestBody;
use HuaweiCloud\SDK\Core\Auth\GlobalCredentials;

class HuaweiProvider implements CdnProviderInterface
{
    private S3ClientInterface $s3;
    private CdnClient $cdn;
    private array $config;

    public function __construct(array $config, ?S3ClientInterface $s3 = null, ?CdnClient $cdn = null)
    {
        $this->config = $config;
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'cn-north-4',
            'endpoint' => 'https://obs.' . ($config['region'] ?? 'cn-north-4') . '.myhuaweicloud.com',
            'credentials' => [
                'key' => $config['ak'] ?? '',
                'secret' => $config['sk'] ?? '',
            ],
        ]);
        $credential = (new GlobalCredentials())->withAk($config['ak'] ?? '')->withSk($config['sk'] ?? '');
        $this->cdn = $cdn ?? CdnClient::builder()
            ->withCredential($credential)
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
            $req = (new RefreshTaskRequest())
                ->withBody((new RefreshTaskRequestBody())->withType('file')->withUrls($urls));
            $resp = $this->cdn->refreshTask($req);
            return [$resp->getRefreshTask()['id'] ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('huawei', 'purge', $e->getMessage());
        }
    }

    public function preload(array $urls): array
    {
        try {
            $req = (new PreheatingTaskRequest())
                ->withBody((new PreheatingTaskRequestBody())->withType('file')->withUrls($urls));
            $resp = $this->cdn->createPreheatingTasks($req);
            return [$resp->getPreheatingTask()['id'] ?? ''];
        } catch (\Throwable $e) {
            throw new CdnException('huawei', 'preload', $e->getMessage());
        }
    }

    public function url(string $key): string
    {
        return "https://{$this->config['domain']}/{$key}";
    }
}
```

- [ ] **Step 4: 运行测试验证通过**

Run: `vendor/bin/phpunit tests/HuaweiProviderTest.php`
Expected: 3 tests PASS

> 华为云 SDK 包内类名（CdnClient 的 V1/V2 命名空间、`refreshTask`/`createPreheatingTasks` 方法名）若与实际不符，`composer` 安装后在 `vendor/huaweicloud/huaweicloud-sdk-php` 下 `grep -r "class CdnClient"` 定位真实类名，同步调整实现与测试。

- [ ] **Step 5: Commit**

```bash
git add app/cdn/HuaweiProvider.php tests/HuaweiProviderTest.php
git commit -m "feat(cdn): HuaweiProvider（OBS 上传 + 刷新/预热 API）"
```

---

### Task 9: 全量测试 + 收尾

**Files:**
- Test: `tests/CdnFactoryTest.php`（已存在）+ 全部 `tests/*ProviderTest.php`

- [ ] **Step 1: 运行全部测试**

```bash
cd /home/wwwroot/game-platform-php/service && vendor/bin/phpunit tests/CdnFactoryTest.php tests/CloudflareProviderTest.php tests/CloudFrontProviderTest.php tests/AliyunProviderTest.php tests/TencentProviderTest.php tests/HuaweiProviderTest.php
```

Expected: 全部 PASS（约 20 tests）。若有失败，修复后重跑。

- [ ] **Step 2: 确认项目全量测试无回归**

```bash
cd /home/wwwroot/game-platform-php/service && vendor/bin/phpunit
```

Expected: 全量 PASS。若失败只允许是与 CDN 无关的既有失败（记录即可，不修复）。

- [ ] **Step 3: Commit**

```bash
cd /home/wwwroot/game-platform-php && git add -A && git commit -m "feat(cdn): 五厂商 CDN 适配器全量通过"
```

（若 Step 2 无新改动则跳过此提交，各 Task 已独立提交。）

- [ ] **Step 4: 同步文档（可选）**

若项目有 `docs/FEATURES.md` 或 `docs/CHANGELOG.md`，在其中追加 CDN 支持条目（厂商清单 + 用法示例）。若无，跳过。

---

## Self-Review 结果（写计划时已检查）

1. **Spec 覆盖**：接口 4 方法 ✓（Task 2）、五厂商适配器 ✓（Task 4-8）、CdnFactory ✓（Task 3）、config/cdn.php ✓（Task 2）、CdnException ✓（Task 2）、依赖 ✓（Task 1）、测试 ✓（Task 3-8）、范围外（对外 API/直传/DNS）无任务——符合设计。
2. **无占位符**：所有步骤含完整代码或精确命令；SDK 类名差异处均有明确排查路径。
3. **类型一致性**：接口签名 `upload(string $key, string $localPath, array $options = []): string` 在全部 Provider 中一致；`CdnFactory::resolve(string $provider, array $config)` 与测试一致；`CdnException($provider, $operation, $message)` 构造参数一致。
