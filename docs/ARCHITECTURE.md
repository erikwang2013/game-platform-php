# 架构文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 系统拓扑

```mermaid
flowchart TB
    subgraph "客户端层"
        A1["Flutter Web PC<br/>管理后台"]
        A2["Flutter Web PC<br/>C端用户平台"]
        A3["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph "网关层 (Nginx)"
        B1["反向代理 + HTTPS<br/>路由分发 + Gzip<br/>静态文件服务"]
    end

    subgraph "应用层"
        C1["admin/ webman<br/>管理后台 :8787<br/>AdminAuth → AdminPermission → OperationLog"]
        C2["service/ webman<br/>C端业务 :8788<br/>UserAuth"]
    end

    subgraph "共享层"
        D1["common/<br/>Model (12) / Middleware / Service"]
    end

    subgraph "存储层"
        E1[("MySQL 8.0<br/>主存储<br/>表前缀 erik_")]
        E2[("Redis<br/>Session / 缓存<br/>限流 / Captcha")]
        E3[("Elasticsearch<br/>全文检索<br/>索引前缀 erik_")]
    end

    A1 & A2 & A3 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 -->|"/admin/*"| C1
    B1 -->|"/api/*"| C2
    C1 & C2 --> D1
    C1 & C2 --> E1 & E2 & E3
```

## 2. 模块架构

### 2.1 admin/ — 管理后台

```
路由层: config/route.php
  ↓
中间件链: Cors → SecurityFilter → RateLimit → AdminAuth → AdminPermission → OperationLog
  ↓
控制器层 (14+5 个):
  ┌─────────────────────────────────────────────────────┐
  │ Dashboard / User / Role / Permission / Config / Log │ ← 原有
  │ Profile / Export / Import / Upload / Health / Docs  │ ← 原有
  │ Game / Withdraw / Payment / PlatformUser / Announce │ ← 新增
  └─────────────────────────────────────────────────────┘
  ↓
共享层: common/model/* (Eloquent ORM)
  ↓
存储层: MySQL / Redis / Elasticsearch
```

**职责**：管理员登录、游戏 CRUD（含区服管理）、提现审核（含阶梯限额）、C端用户管理、KYC 审核、支付方式管理、公告管理、数据导出、仪表盘

### 2.2 service/ — C端业务端

```
路由层: config/route.php
  ↓
中间件链: Cors → SecurityFilter → RateLimit → ApiVersion → [UserAuth]
  ↓
控制器层 (13 个):
  ┌──────────────────────────────────────────────────────┐
  │ Auth / Wallet / Deposit / Exchange / Withdraw        │
  │ Game / User / Announcement / Captcha                  │
  │ OAuth / Identity / Payment / GamePlayLog             │ ← 标准版新增
  └──────────────────────────────────────────────────────┘
  ↓
共享层: common/model/* (Eloquent ORM)
  ↓
存储层: MySQL / Redis / Elasticsearch
```

**职责**：用户注册登录、钱包管理、充值下单、平台币⇄游戏币兑换、提现申请、游戏列表/详情/启动、公告浏览

### 2.3 common/ — 共享层

```
common/
├── model/
│   ├── User.php              # C端用户（SoftDeletes, Encryptable）
│   ├── UserWallet.php        # 平台币钱包（乐观锁 addBalance/deductBalance）
│   ├── UserGameWallet.php    # 游戏币钱包
│   ├── Game.php              # 游戏（Encryptable api_key/secret）
│   ├── GameCurrency.php      # 游戏币种（汇率 + 抽成）
│   ├── DepositOrder.php      # 充值订单
│   ├── WithdrawOrder.php     # 提现订单
│   ├── ExchangeRecord.php    # 兑换记录
│   ├── Transaction.php       # 平台流水
│   ├── PaymentMethod.php     # 支付方式
│   ├── Announcement.php      # 公告
│   ├── PlatformConfig.php    # 平台配置（类型化 get/set）
│   ├── Language.php          # 语言定义
│   └── Translation.php       # 翻译文本
├── middleware/
│   └── UserAuth.php          # C端 JWT 认证中间件
└── service/
    └── TranslationService.php # 国际化翻译服务（Redis缓存 + DB回退）
```

**设计原则**：
- Model 与数据表一一对应，定义 casts（加密/类型）和 relations（关联关系）
- admin/ 和 service/ 通过 PSR-4 autoload 引用，无需代码复制
- 新增 Model 统一放在 common/ 下，两个应用同步可用

## 3. 中间件执行链

### admin/（管理后台）

```
请求 → Cors (跨域)
     → SecurityFilter (方法白名单+攻击检测→405/403)
     → RateLimit (Redis 滑动窗口限流→429)
     → AdminAuth (JWT 认证→401)
     → AdminPermission (RBAC 鉴权, Redis 60s 缓存→403)
     → OperationLog (操作日志自动记录)
     → Controller → 响应
```

### service/（C端业务端）

```
请求 → Cors (跨域)
     → SecurityFilter (方法白名单+攻击检测→405/403)
     → RateLimit (Redis 滑动窗口限流→429)
     → LanguageMiddleware (检测语言→设置 locale)
     → ApiVersion (API-Version 头校验→400)
     → [UserAuth] (JWT 认证→401, 仅需认证的接口)
     → Controller → 响应
```

## 4. 核心数据流

### 4.1 充值流程

```
用户 → POST /api/deposit/create → 生成订单 (status=pending)
     → 跳转第三方支付 (Stripe/PayPal)
     → 支付成功 → 第三方回调 /api/payment/callback
     → 验证签名 → 更新订单 (status=confirmed)
     → UserWallet::addBalance() → 平台币到账
     → 记录 Transaction (type=deposit)
```

### 4.2 兑换流程

```
用户 → POST /api/exchange/quote → 询价（试算汇率+手续费）
     → 确认 → POST /api/exchange/buy(或sell)
     → DB::beginTransaction()
     ├─ 扣减源币种 (UserWallet::deductBalance 或 deductGameBalance)
     ├─ 增加目标币种 (addBalance 或 addGameBalance)
     ├─ 记录 ExchangeRecord
     ├─ 记录 Transaction
     └─ DB::commit()（任何步骤失败 → rollBack）
```

### 4.3 提现流程

```
用户 → POST /api/withdraw/apply
     → 检查全局开关 (PlatformConfig::get)
     → 检查限额 (min_amount / daily_limit)
     → 检查余额
     → 扣减余额
     → 金额 < 阈值 → status=approved (自动)
     → 金额 >= 阈值 → status=pending (人工审核)
     → 记录 Transaction

管理员 → PUT /admin/withdraw/review
       → approve: 标记完成 (基础版手动打款)
       → reject: 退回平台币 + 记录退款流水
```

## 5. 数据库 ER 关系

```
erik_user ──┬── 1:1 ── erik_user_wallet
            ├── 1:N ── erik_user_game_wallet
            ├── 1:N ── erik_deposit_order
            ├── 1:N ── erik_withdraw_order
            │             └── reviewer_id → erik_admin_user
            ├── 1:N ── erik_exchange_record
            └── 1:N ── erik_transaction

erik_game ──┬── 1:N ── erik_game_currency
            ├── 1:N ── erik_user_game_wallet
            └── 1:N ── erik_exchange_record

erik_game_currency ── 1:N ── erik_exchange_record
erik_payment_method ── 1:N ── erik_deposit_order
```

## 6. 部署架构

### 开发环境

```
单机部署:
  admin/    :8787 (webman, 32 workers)
  service/  :8788 (webman, 32 workers)
  MySQL     :3306
  Redis     :6379
```

### 生产环境

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web 服务器 (Nginx)"
        NGX["反向代理 :443 HTTPS<br/>静态文件服务<br/>gzip + CSP + HSTS"]
    end

    subgraph "应用服务器 (可横向扩展)"
        ADM1["admin worker 1 :8787"]
        ADM2["admin worker 2 :8787"]
        SVC1["service worker 1 :8788"]
        SVC2["service worker 2 :8788"]
    end

    subgraph "数据层"
        MYSQL["MySQL 8.0 主从复制"]
        REDIS["Redis 7.x 哨兵模式"]
        ES["Elasticsearch 8.x 3节点集群"]
    end

    subgraph "监控"
        MON["Grafana + Prometheus<br/>健康检查 /metrics"]
    end

    DNS --> NGX
    NGX --> ADM1 & ADM2 & SVC1 & SVC2
    ADM1 & ADM2 & SVC1 & SVC2 --> MYSQL & REDIS & ES
    ADM1 & ADM2 & SVC1 & SVC2 --> MON
```

## 7. 测试架构

```
tests/
├── bootstrap.php                  # PHPUnit 引导（自动加载、.env、Model别名）
├── PlatformTest.php               # 56 个业务逻辑测试
├── BackendEnhancementTest.php     # 23 个加密/ID服务测试
├── CaptchaTest.php                # 7 个验证码测试
├── EncryptionServiceTest.php      # 6 个加解密测试
├── EnvConfigTest.php              # 4 个环境配置测试
├── HashidsServiceTest.php         # 8 个 ID 编解码测试
└── SnowflakeServiceTest.php       # 6 个 Snowflake ID 测试
```

运行：`cd admin && phpunit --bootstrap tests/bootstrap.php tests/`

## 8. 端口分配

| 服务 | 端口 | 说明 |
|------|------|------|
| admin/ | 8787 | 管理后台 API |
| service/ | 8788 | C端业务 API |
| leaderboard-ws | 8789 | WebSocket 实时排行榜 |
| MySQL | 3306 | 主数据库 |
| Redis | 6379 | 缓存/限流/WebSocket |
| Elasticsearch | 9200 | 全文检索 |

## 9. API 文档

使用 `hg/apidoc` 通过控制器注解自动生成交互式 API 文档：

| 文档 | 地址 | 控制器 | 分组 |
|------|------|--------|------|
| 管理后台 | :8787/apidoc/ | 25(已注解)/26 | 25 组 |
| C端业务 | :8788/apidoc/ | 22 | 16 组 |

配置：
- admin: `config/plugin/hg/apidoc/app.php` → 扫描 `app\admin\controller`
- service: `config/plugin/hg/apidoc/app.php` → 扫描 `app\api\v1\controller`
- 注解格式：`@Apidoc\Title` / `@Apidoc\Group` / `@Apidoc\Url` / `@Apidoc\Method` / `@Apidoc\Param`
- 密码保护：`admin123`
- 管理后台 79 个 API 端点，C端 54 个

## 10. 部署架构

### Docker Compose（7 服务）

```yaml
nginx (80/443) → admin (8787) + service (8788) + static files
leaderboard-ws (8789) — WebSocket 排行榜实时推送
mysql (3306) — 主数据库，数据卷持久化
redis (6379) — 缓存/限流/WebSocket
elasticsearch (9200) — 全文检索
```

启动：
```bash
docker-compose up -d
```

### 新增生产服务

| 服务 | 类型 | 说明 |
|------|------|------|
| NotificationService | common/service | 站内通知 + 邮件发送 |
| LeaderboardWebSocket | app/process | WebSocket 实时排行榜推送 |
| OAuth (Google/Facebook/Apple) | controller | 真实token交换 + mock回退 |
| Stripe/PayPal Webhook | controller | 签名验证 |
| 2FA TOTP | controller | Google Authenticator |
