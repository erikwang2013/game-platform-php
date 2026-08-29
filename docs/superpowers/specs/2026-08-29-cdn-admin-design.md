# CDN 管理端配置设计文档

日期：2026-08-29
状态：已确认

## 目标

将 CDN 厂商配置从静态 `config/cdn.php`（env 变量）迁移到管理端可配置、可管理：

- 新建 `cdn_provider` 表，一行一家厂商（5 家预设种子数据）
- admin 面板：列表/启停/新增/编辑/删除/连通测试 6 个操作
- service 侧纯 DB 读取（删除 config/cdn.php，消除双源漂移）
- `CdnFactory::resolve()` 签名不变，只换调用方

## 表结构

`cdn_provider`（镜像 payment_method 模式，config 字段 Encryptable 加密存储）：

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT | hashid 编码对外 |
| name | VARCHAR(50) NOT NULL | 显示名称 |
| provider | VARCHAR(30) NOT NULL | cloudflare/cloudfront/aliyun/tencent/huawei，UNIQUE |
| config | TEXT | 加密 JSON，key 结构沿用 config/cdn.php |
| status | TINYINT NOT NULL DEFAULT 1 | 1 启用 / 0 停用 |
| sort | INT NOT NULL DEFAULT 0 | 排序 |
| created_at / updated_at | DATETIME NULL | |

config JSON key 结构（沿用 config/cdn.php，密钥值直接存明文在 JSON 内，入库时整体加密）：

- cloudflare: bucket/domain/account_id/api_token/zone_id/access_key_id/access_key_secret
- cloudfront: bucket/domain/distribution_id/access_key_id/access_key_secret/region
- aliyun: bucket/domain/access_key_id/access_key_secret/region
- tencent: bucket/domain/secret_id/secret_key/region
- huawei: bucket/domain/ak/sk/region

模型非共享（admin / service 各一份），沿用既有约定。

## 种子数据

5 家厂商预设：名称 + provider + 默认 bucket/domain（占位域名）+ 空凭据 + status=0（停用，管理员填凭据后启用）。随安装 SQL 写入，含存量库升级脚本（与生态扩展 10 张表同机制，`install/` 目录）。

## Admin API

`admin/app/admin/controller/CdnProviderController.php`（镜像 PaymentController 模式：BaseController、hashid encode/decode、validator、success/fail、@Apidoc）：

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/cdn/provider/list | 列表：name/provider/status/sort/domain（config 不返回） |
| POST | /admin/cdn/provider/toggle | 启停：{id} |
| POST | /admin/cdn/provider/create | 新增：name/provider/config/status/sort，provider 查重 |
| PUT | /admin/cdn/provider/{hashid} | 编辑：config 字段留空 = 不修改 |
| DELETE | /admin/cdn/provider/{hashid} | 删除 |
| POST | /admin/cdn/provider/test | 连通测试：{id} |

连通测试实现：`CdnFactory::resolve($provider, $解密config)` → 调 `purge(['https://{domain}/__cdn_probe.txt'])`，成功返回任务 ID 列表，失败捕获 `CdnException` 返回错误信息。复用既有 purge 路径，零新增 SDK 调用。

## service 侧消费

新增 `service/app/cdn/CdnService.php`：

- `provider(string $provider): CdnProviderInterface` — 从 cdn_provider 表查 status=1 记录，解密 config，`CdnFactory::resolve()`；无记录/未启用抛 `CdnException`
- 业务代码只依赖 CdnService，不直接接触 CdnFactory/config

删除 `service/config/cdn.php`（及对应 env 变量引用，检查全库引用后移除）。

## Flutter admin 前端

新增「CDN 配置」页，镜像支付管理页：列表（厂商/名称/状态/域名 + 启停/编辑/删除/测试按钮）、新增/编辑表单（凭据输入）。

## 范围外

- 上传 API / 客户端直传（上轮已明确）
- 连通测试的异步任务状态追踪（同步调用即返回）
- 多实例（同厂商多 bucket/域名）——一行一家，够用再加
- 13 语言 FEATURES 同步（沿用约定，zh/en 先行）

## 测试

- admin：CdnProviderControllerTest — list/toggle/create（查重）/update（密钥留空不覆盖）/delete/test（成功+失败）
- service：CdnServiceTest — 读启用厂商并解密、未启用抛异常、无记录抛异常
- 既有 CdnFactoryTest 不受影响（resolve 签名不变）
