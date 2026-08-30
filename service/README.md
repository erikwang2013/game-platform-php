# service/ — C端用户平台 API 服务
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C端用户平台 API 服务，基于 webman v2（Workerman）的高性能 PHP 后端，面向用户提供完整的游戏聚合平台能力：注册登录、钱包、充值、提现、兑换、游戏、排行榜、优惠券、客服工单、VIP、成就、社交与公告。

## 功能清单

| 模块 | 说明 |
|------|------|
| 用户 | 注册/登录（用户名密码 + 7 平台 OAuth + 2FA TOTP）、个人资料 |
| 钱包 | 平台币钱包（乐观锁）+ 游戏币钱包 + 流水记录 |
| 充值 | 13 家支付网关（Stripe/PayPal/NowPayments/Coinbase 等）回调验签、自动到账 |
| 提现 | 申请 → 审核 → 打款，KYC 阶梯限额 |
| 兑换 | 平台币 ⇄ 游戏币实时询价，VIP 折扣与汇率加成 |
| 游戏 | 游戏列表/分类/搜索、游戏记录、Provider 结算回调 |
| 排行榜 | 日/周/月/总榜 + WebSocket 实时推送 |
| 优惠券 | 固定金额 + 比例折扣、限时限量 |
| 工单 | 用户创建/回复客服工单 |
| VIP | 5 级忠诚度、经验值累计、兑换折扣 |
| 成就 | 12 个内置成就、事件驱动检测 |
| 社交 | 好友系统 + WebSocket 实时私信 |
| 公告 | 站内公告 + 站内信/邮件通知 |

## 技术栈

- PHP 8.3+ / webman v2（workerman/webman）
- MySQL 8.0+（表前缀 `game_`，BIGINT 非自增主键）
- Redis（Session / 缓存 / 限流）
- ClickHouse（OLAP 分析 / 概率计算）
- Elasticsearch（全文检索）
- JWT 认证 + HMAC-SHA256 Provider 签名

## 项目结构

```
service/
├── app/
│   ├── api/v1/controller/  # C端 API 控制器（35 个）
│   ├── middleware/         # 中间件（Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth）
│   ├── model/              # 数据模型
│   ├── service/            # 业务服务（VIP/排行榜/风控/通知等）
│   ├── event/              # 事件总线（EventBus Redis Pub/Sub）
│   ├── provider/           # 游戏 Provider 层
│   └── payment/            # 支付网关
├── common/                 # 共享服务目录（实现在 erik/platform-common 包）
├── config/                 # 配置文件
├── public/                 # Web 入口
├── tests/                  # PHPUnit 测试
├── start.php               # 启动入口
└── composer.json
```

## 一键安装

推荐使用项目根目录的一键安装向导（在项目根目录执行）：

```bash
# 1. 启动安装向导
php -S 0.0.0.0:8888 -t install/

# 2. 浏览器打开 http://localhost:8888
#    按向导完成：环境检查 → 数据库配置 → 管理员账户设置 → 自动安装
```

或使用 Docker Compose 一键启动（项目根目录）：

```bash
docker compose up -d
```

## 手动安装

```bash
# 1. 安装依赖
cd service && composer install

# 2. 配置环境变量
cp .env.example .env
# 编辑 .env：数据库连接信息、JWT 密钥等

# 3. 启动服务（默认端口 8788）
php start.php start        # 前台运行
php start.php start -d     # 后台运行
```

## 使用说明

- 接口文档：`docs/API.md`（完整 API 参考）
- 在线文档：http://localhost:8788/apidoc/（hg/apidoc 交互式文档）
- 健康检查：`GET http://localhost:8788/health`
- C端前端：`apps/flutter/platform/`（Flutter Web 用户平台）
- 管理后台：`admin/`（管理后台与 `admin/apps/flutter/` 前端）

## 测试

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
