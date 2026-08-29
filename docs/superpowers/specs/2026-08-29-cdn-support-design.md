# CDN 支持设计文档

日期：2026-08-29
状态：已确认

## 目标

为游戏平台接入全球主流 CDN 云服务商，提供统一的服务端 CDN 组件（仅内部调用，不对外开 API），覆盖：

- 静态资源加速（前端 JS/CSS/图片分发）
- 上传文件存储 + 分发（用户头像、聊天图片等）
- 游戏资源分发（xiaoxiaole 关卡/素材包）
- 全站加速/API 边缘：DNS 层配置（CNAME/代理模式），代码层不做，部署文档说明

## 厂商范围（五家）

| 厂商 | 对象存储 | 缓存管理 API |
|------|----------|--------------|
| Cloudflare | R2（S3 兼容） | purge_cache（HTTP 直调，Guzzle） |
| AWS | S3 | CloudFront createInvalidation（aws-sdk-php） |
| 阿里云 | OSS（S3 兼容端点） | RefreshObjectCaches（alibabacloud/cdn SDK） |
| 腾讯云 | COS（S3 兼容） | PurgeUrlsCache（tencentcloud-sdk-php CDN 模块） |
| 华为云 | OBS（S3 兼容） | Refresh（huaweicloud OBS/CDN SDK） |

关键技术点：五家对象存储全部兼容 AWS S3 API，存储层用 aws-sdk-php 一个依赖覆盖五家（各自配 endpoint）。

## 结构

镜像 `app/payment/` 多厂商模式：

```
service/app/cdn/
├── CdnProviderInterface.php   # 统一接口
├── CdnFactory.php             # match($provider) 解析
├── CloudflareProvider.php     # R2 存储 + purge_cache API
├── CloudFrontProvider.php     # S3 存储 + invalidation API
├── AliyunProvider.php         # OSS 存储 + RefreshObjectCaches API
├── TencentProvider.php        # COS 存储 + PurgeUrlsCache API
├── HuaweiProvider.php         # OBS 存储 + Refresh API
└── CdnException.php
```

## 接口

```php
interface CdnProviderInterface
{
    /** 上传本地文件到对象存储，返回 CDN 访问 URL */
    public function upload(string $key, string $localPath, array $options = []): string;
    /** 缓存刷新（purge），返回任务 ID 列表 */
    public function purge(array $urls): array;
    /** 资源预热（preload），返回任务 ID 列表 */
    public function preload(array $urls): array;
    /** 按 key 生成 CDN URL（不上传，仅拼接） */
    public function url(string $key): string;
}
```

## 配置

`config/cdn.php`：每厂商 credentials（密钥走环境变量）、bucket、CDN 域名、`default` 厂商。

```php
return [
    'default' => 'cloudflare',
    'providers' => [
        'cloudflare' => [
            'bucket' => 'static',
            'domain' => 'cdn.example.com',
            'account_id' => env('CF_ACCOUNT_ID'),
            'api_token' => env('CF_API_TOKEN'),
            'zone_id'   => env('CF_ZONE_ID'),
            's3' => ['endpoint' => 'https://{account_id}.r2.cloudflarestorage.com'],
        ],
        'aliyun' => [
            'bucket' => 'static',
            'domain' => 'cdn.aliyun.example.com',
            'access_key_id'     => env('ALI_AK'),
            'access_key_secret' => env('ALI_SK'),
            'region' => 'oss-cn-hangzhou',
        ],
        // tencent / huawei / cloudfront 同构
    ],
];
```

## 数据流

```
业务代码 → CdnFactory::resolve('aliyun')
  → upload('avatar/1.jpg', '/tmp/x.jpg')
    → OSS SDK putObject → 返回 https://cdn.example.com/avatar/1.jpg
  → purge(['https://cdn.example.com/avatar/1.jpg']) → 返回刷新任务 ID
```

- key 由调用方决定（如 `avatar/{uid}.jpg`、`game/{version}/res.zip`），bucket 由配置指定
- 厂商可在配置中切换，同一 key 可换厂商重传

## 错误处理

- 所有 SDK/HTTP 异常包装为 `CdnException`（携带厂商名 + 操作 + 原始错误），上层 catch 后记日志
- 上传失败不吞异常（业务层需要知道文件没存上）
- purge/preload 失败仅记日志不阻断业务（缓存失效可重试）

## 依赖

- `aws/aws-sdk-php` — S3 客户端（五家存储）+ CloudFront invalidation 客户端
- `alibabacloud/cdn` — 阿里云 CDN 刷新/预热
- `tencentcloud-sdk-php`（CDN 模块）— 腾讯云刷新/预热
- 华为云 OBS SDK + CDN SDK
- Cloudflare — Guzzle 直调（已装）

## 测试

镜像支付网关测试风格（`service/tests/`）：

- `CdnFactoryTest`：resolve 各厂商 + 未知厂商抛异常
- `CloudflareProviderTest` / `CloudFrontProviderTest` 等：mock SDK client 断言 upload 参数、purge URL 列表、URL 生成格式
- `CdnConfigTest`：配置缺密钥时报错

## 范围外

- 对外上传 API（`/api/v1/file/upload`）——仅服务端组件
- 客户端直传（签名 URL 预上传）
- 全站加速 DNS 层配置（部署文档说明）
