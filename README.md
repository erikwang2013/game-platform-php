# 全球游戏聚合平台 (Global Game Platform)

[English](README_EN.md) | 中文

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

全球通用、国际化的游戏聚合平台。用户注册后在平台充值兑换游戏币，用游戏币玩游戏、赚取游戏币，游戏币可转回钱包提现。后台提供完整的游戏管理、提现审核、用户管理和支付管理功能。支持多语言切换（英文/中文）。

## 版本策略

| 版本 | 目标 | 状态 |
|------|------|------|
| 基础版 (MVP) | 跑通核心闭环：注册→充值→兑换→游戏→提现→审核 | 已完成 |
| 标准版 | 生产可用：全球化支付、第三方游戏SDK、基础风控、三端前端 | 规划中 |
| 完整版 | 完全体：多语言、排行榜、优惠券、完整风控、全量功能 | 规划中 |

## 技术栈

### 后端
- PHP 8.3+, webman v2 (workerman/webman)
- MySQL 8.0+ (表前缀 `erik_`，BIGINT 非自增主键)
- Redis (Session / 缓存 / 限流)
- Elasticsearch (全文检索)
- JWT 认证 + RBAC 权限控制
- 数据加密：API 传输层 AES-256-CBC + 数据库存储层 AES-128-ECB

### 前端
- Flutter 3.x (Web PC 风格)
- HarmonyOS ArkTS (移动端)
- 响应式布局 (Phone / Tablet / Desktop)
- 国际化 (i18n)：英文 / 简体中文切换

### 核心组件
- `erikwang2013/snowflake-php` — 全局唯一 BIGINT ID 生成
- `erikwang2013/hashids` — API 层 ID 加解密
- `erikwang2013/jwt-webman` — JWT 认证
- `erikwang2013/encryption` — API 敏感数据加解密
- `erikwang2013/encryptable` — 数据库敏感字段加解密
- `erikwang2013/webman-scout` — Elasticsearch 同步与查询
- `erikwang2013/season` — 国家旗帜
- `erikwang2013/security-php` — 安全工具检测
- `erikwang2013/poster-php` — 敏感操作随机验证

## 项目结构

```
game-platform-php/
├── admin/                     # 管理后台 (webman v2, 端口 8787)
│   ├── app/admin/controller/  #   管理端控制器
│   ├── app/middleware/        #   中间件 (Cors/Security/RateLimit/Auth/Permission)
│   ├── config/                #   配置文件
│   ├── database/migrations/   #   SQL 迁移文件
│   └── apps/flutter/          #   Flutter Web PC 管理后台
│
├── service/                   # C端业务端 (webman v2, 端口 8788)
│   ├── app/api/v1/controller/ #   C端 API 控制器
│   ├── app/middleware/        #   中间件
│   └── config/                #   配置文件
│
├── common/                    # 共享层 (PSR-4 autoload)
│   ├── model/                 #   数据模型 (25个)
│   ├── middleware/            #   共享中间件 (UserAuth)
│   └── service/               #   共享服务 (4个)
│
├── apps/
│   └── flutter/platform/      # Flutter Web PC C端用户平台
│
├── docs/                      # 项目文档
│   ├── ARCHITECTURE.md        #   架构文档
│   ├── ARCHITECTURE-DESIGN.md #   架构设计文档
│   ├── FEATURES.md            #   功能文档
│   ├── FEATURE-DESIGN.md      #   功能设计文档
│   └── API.md                 #   接口文档
│
└── admin/docs/superpowers/    # 开发规范与计划
    ├── specs/                 #   设计规范
    └── plans/                 #   实现计划
```

## 快速开始

### 环境要求
- PHP 8.3+
- MySQL 8.0+
- Redis 6.0+
- Composer 2.x
- Flutter SDK 3.x (前端)

### 1. 数据库初始化

```bash
# 创建数据库
mysql -u root -e "CREATE DATABASE IF NOT EXISTS game_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 执行迁移（按编号顺序）
mysql -u root game_platform < admin/database/migrations/2026_05_16_000000_init_tables.sql
mysql -u root game_platform < admin/database/migrations/2026_05_22_000003_platform_tables.sql
mysql -u root game_platform < admin/database/migrations/2026_05_22_000004_i18n_tables.sql
```

### 2. 后端启动

```bash
# 管理后台 (端口 8787)
cd admin
cp .env.example .env   # 编辑数据库连接信息
composer install
php start.php start -d

# C端业务端 (端口 8788)
cd service
cp .env.example .env   # 编辑数据库连接信息
composer install
php start.php start -d
```

### 3. 前端启动

```bash
# 管理后台 (Flutter Web PC)
cd admin/apps/flutter
flutter pub get
flutter run -d chrome

# C端用户平台 (Flutter Web PC)
cd apps/flutter/platform
flutter pub get
flutter run -d chrome
```

### 4. 验证

```bash
# 测试管理后台
curl http://localhost:8787/health

# 测试C端业务
curl http://localhost:8788/health

# 测试用户注册
curl -X POST http://localhost:8788/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"123456"}'
```

## 安全特性

- **18 层纵深防御**：XSS/SQL注入/CSRF/路径遍历/命令注入检测拦截
- **HTTP 方法白名单**：仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD
- **JWT 认证**：access_token 2h + refresh_token 14d，并发会话限制
- **RBAC 权限**：method.path 粒度权限控制，Redis 60s 缓存
- **点击验证码**：登录/注册强制人机验证
- **密码二次确认**：敏感操作需输入密码确认
- **数据加密**：传输层 AES-256-CBC + 存储层 AES-128-ECB
- **ID 加密**：Snowflake 生成 + Hashids 编码，外部不可逆推
- **钱包乐观锁**：防止并发扣款/重复到账
- **操作审计**：全量操作日志，8 平台来源端自动检测
- **限流**：Redis 滑动窗口，Lua 原子化
- **CSP 头**：Content-Security-Policy 防 XSS
- **账号安全**：连续5次登录失败锁定15分钟

## 业务模型

```
法币 (USD/CNY/EUR...)
  │  充值(Stripe/PayPal/支付宝/微信)
  ▼
平台币 (统一，精度 decimal(18,4))
  │  兑换（含汇率 + 平台抽成差价）
  ▼
游戏币 (每种游戏独立，独立汇率)
  │  玩游戏赚/花
  ▼
平台币 ← 兑回 → 提现（审核/自动）
```

## 文档索引

| 文档 | 说明 |
|------|------|
| [版本对比](docs/VERSIONS.md) | 基础版/标准版/完整版功能对比 |
| [架构设计文档](docs/ARCHITECTURE-DESIGN.md) | 架构选型理由与设计决策 |
| [架构文档](docs/ARCHITECTURE.md) | 系统拓扑、模块架构、数据流 |
| [功能设计文档](docs/FEATURE-DESIGN.md) | 业务模型、功能规格、流程设计 |
| [功能文档](docs/FEATURES.md) | 功能清单、模块说明、用户旅程 |
| [接口文档](docs/API.md) | 完整 API 参考 (129个端点) |
| [在线文档](http://localhost:8787/apidoc/) | 管理后台 hg/apidoc 交互式文档 (25组79端点) |
| [在线文档](http://localhost:8788/apidoc/) | C端业务 hg/apidoc 交互式文档 (16组50端点) |
| [部署文档](docs/DEPLOYMENT.md) | Docker + 手动 + Nginx + 监控 |
| [设计规范](admin/docs/superpowers/specs/2026-05-22-game-platform-design.md) | 完整设计规范 |
| [实现计划](admin/docs/superpowers/plans/2026-05-22-game-platform-plan.md) | 详细实现计划 |
