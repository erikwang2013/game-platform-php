# Changelog
<!-- lang-nav -->

Languages: **中文** · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

人类可读变更记录。PHP 不 import 本文件。对应 PROJECT-PLAN P2-21。

## [1.1] — 2026-08-07

- Redis 插件接入、分析服务、Redis 降级、测试修复。

## [1.1] security / ops — 2026-08-18

### 安全

- 支付回调：provider 白名单（stripe/paypal）、fail-closed 验签、金额核对、入账事务化、Stripe 时间戳 ±300s 防重放。
- JWT：`JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` 缺失或为默认值时拒绝启动。
- Apple id_token：JWKS（RS256）验签 + aud/iss/exp。
- Webhook：仅 https 公网 URL，拒绝内网/保留地址（SSRF）。
- 2FA：TOTP HMAC 使用 RFC 4648 Base32 解码后的密钥；`/api/2fa/verify` 逐用户失败锁定（5 次 / 15 分钟，Redis 故障 fail-closed）。
- 提现：审核/打款条件 UPDATE 状态原子翻转；可选双重审核（`withdraw.require_dual_review`）；申请侧 Redis 用户锁防限额并发突破。
- 限流：Redis 故障 fail-closed。

### 可用性

- admin 分析服务 12 条 `/admin/analytics/*` 路由挂载。
- 模型去掉硬编码 `game_` 前缀；DepositLog 审计落库；Test model 删除。

### 可观测

- `GET /metrics` 增加待审核提现、今日确认充值（COUNT 查询 Redis 30s 缓存）、事件 emit/consume 计数、`memory_usage`、`info version=1.1`。
- FeatureFlag：`inRollout` / `abTest` 按 crc32 分桶读取 `feature.{name}_percent`。
- EventBus `emit` / `consume` 对 Redis `metrics:event_emit_total` / `metrics:event_consume_total` 做 INCR。

### 客户端 / 共享（同日补齐）

- Flutter Platform：`app_pages.dart` 路由表；补 2FA 设置/校验、优惠券、排行榜、通知、OAuth 回调页；大厅入口已挂导航。
- HarmonyOS C 端：`apps/harmonyos/` 五页（登录/大厅/详情/钱包/个人），默认 `BASE_URL` 指向 service `8788`。
- 共享层：`packages/platform-common`（`erik/platform-common` path repo）抽出 DepositLog / GameDashboard / Probability / GamePlayLog；model 仍双份。
- ClickHouse：composer 依赖已摘除；分析继续走 MySQL 实时聚合。
- CI：admin / service 分 job 跑 phpunit，失败即阻断。

### 仍存缺口

- admin/service **模型**仍双份（仅部分 `common/service` 入 path 包）。
- `webman/queue` 未接线；概率/留存未迁 OLAP。
- PROJECT-PLAN / VERSIONS / 审计报告部分段落仍可能滞后于本 CHANGELOG，以本文件与磁盘为准。

## [1.1] resilience — 2026-08-27

### 稳定性

- 共享层新增 `CircuitBreaker`（Redis 状态存储，阈值 5 / 窗口 30s，Redis 不可用 fail-open）与 `Retry`（指数退避，仅网络类异常可重试，上限 5 次），位于 `packages/platform-common/src/`。
- 降级开关 `feature.provider_mock`：PushService（FCM/APNs/HarmonyOS）、PayoutService（PayPal）、ThirdPartyProvider 接入 mock 短路，`on` 时跳过真实网络调用。
- 修复 11 处 `getenv($name, '')` 第二参类型缺陷（strict_types 下必抛 TypeError）；PushService mock 检查移入 try/catch。
- 新增测试：CircuitBreakerTest / RetryTest / ResilienceMockTest，service 套件 45 → 60 用例全绿（报告见 [test-reports/resilience.md](test-reports/resilience.md)）。

## [1.1] payments — 2026-08-29

- 多支付网关：接入 Stripe Checkout / NOWPayments（USDT TRC20+ERC20）/ Coinbase Commerce（USDC）、Alipay/WeChat Pay（Stripe Checkout APM）。
- 后台支付方式 CRUD + 国家可见性 + 金额区间；充值订单创建即回填 checkout_url / expires_at。
- 新增迁移 install/migrations/2026_08_29_multi_payment.sql（需执行）。

## [1.1] features — 2026-08-29

- 田园消消乐 P0 小游戏：领域引擎 + 四关设计 + Vitest 单测（`game/xiaoxiaole/`）。
- 一键安装向导：浏览器建管理员、存量库升级（修复 HY093 绑定参数、Unknown column 'countries'）、install.lock 防重装。
- CI：push 自动增量 tag + GitHub Release 发布。
- 基础设施：数据库更名 game-platform、`game_` 表前缀统一。
- 文档同步：FEATURES.md 13 语言补齐容错（熔断/重试/降级开关）、支付方式后台 CRUD、小游戏、一键安装、CI 行（对应上 [1.1] resilience / payments 条目）。
