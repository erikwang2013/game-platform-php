# 版本对比
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 总览

| | 基础版 (Lite) | 标准版 (Standard) | 完整版 (Full) |
|------|------|------|------|
| 数据表 (install.sql) | 19 | 29 | **43**（非文档曾写的 52） |
| API 端点 | 38 | 54 | ~149 (admin+service，含 Webhook/Provider) |
| 后端控制器 | 14 | 22 | admin 32 + service 30 |
| 数据模型 | 非共享 | 非共享 | **admin 46 / service 44 各一份，无共享层** |
| 共享 Service | 无共享层 | 无共享层 | `packages/platform-common` 单一共享包 |
| Admin 前端页面 | 11 | 13 | 15 |
| Platform 前端页面 | 8 | 10 | 10 |
| HarmonyOS (admin) | - | 登录+仪表盘 | **8 页** `admin/apps/harmonyos/` |
| HarmonyOS (C端) | - | - | **5 页** `apps/harmonyos/`（登录/游戏大厅/详情/钱包/我的） |
| Docker 服务 | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| 测试用例 | 60 | 60 | admin ~132；service 3 |

---

## 用户认证

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 用户名密码注册/登录 | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| 点击验证码 | stub | stub | ✓ poster-php |
| 账号锁定 (5次/15分钟) | ✓ | ✓ | ✓ |
| 会话限制 (3并发) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7平台 (含 X/MS/LinkedIn/GitHub) |
| 2FA TOTP 双因素认证 | - | - | ✓ |
| GDPR 数据导出/注销 | - | - | ✓ |

---

## 钱包与资金

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 平台币钱包 | ✓ | ✓ | ✓ |
| 钱包乐观锁 | ✓ | ✓ | ✓ |
| 流水记录 | ✓ | ✓ | ✓ |
| 游戏币钱包 | ✓ | ✓ | ✓ |
| 充值订单创建(即回填 checkout_url/expires_at) | ✓ | ✓ | ✓ |
| 充值回调自动到账 | - | ✓ 手动 | ✓ Stripe(含 Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook验签 |
| 兑换询价/买入/卖出 | ✓ | ✓ | ✓ |
| 兑换差价收益 | ✓ | ✓ | ✓ |
| 提现申请 | ✓ | ✓ | ✓ |
| 全局提现开关 | ✓ | ✓ | ✓ |
| 提现审核 | ✓ 手动 | ✓ 手动 | ✓ 批量+手动 |
| KYC阶梯限额 | - | ✓ 3级 | ✓ |
| 提现手续费 | - | - | ✓ |
| PDF收据 | - | - | ✓ |

---

## 游戏管理

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 游戏 CRUD | ✓ | ✓ | ✓ |
| 游戏币种管理 | ✓ | ✓ | ✓ |
| C端游戏列表/详情 | ✓ | ✓ | ✓ |
| 游戏启动 | ✓ | ✓ | ✓ |
| 游戏分类 (10类) | - | - | ✓ |
| 分类筛选 | - | - | ✓ |
| 游戏区服管理 | - | ✓ | ✓ |
| 游戏记录追踪 | - | ✓ | ✓ |
| ES 全文搜索 | - | - | ✓ |
| 搜索建议 | - | - | ✓ |
| 第三方游戏 Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## 运营工具

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 公告管理 | ✓ | ✓ | ✓ |
| 仪表盘 | ✓ 管理后台 | ✓ 管理后台 | ✓ 管理+平台 |
| Excel 导出 | ✓ | ✓ | ✓ |
| PDF 导出 | ✓ | ✓ | ✓ |
| 仪表盘真实图表 | - | - | ✓ fl_chart |
| 优惠券系统 | - | - | ✓ |
| 排行榜 (日/周/月/总) | - | - | ✓ Redis缓存 |
| WebSocket 实时排行榜 | - | - | ✓ 端口8789 |
| 通知系统 (站内+邮件) | - | - | ✓ |
| 推荐返利 | - | - | ✓ |
| 日统计快照 | - | ✓ | ✓ |
| 数据报表 (汇总/日报/CSV导出) | - | - | ✓ |
| C端平台统计 | - | - | ✓ |
| 平台收益追踪 | - | - | ✓ |

---

## 安全合规

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 18层纵深防御 | ✓ | ✓ | ✓ |
| RBAC 权限控制 | ✓ | ✓ | ✓ |
| 操作审计日志 | ✓ | ✓ | ✓ |
| 8平台来源端检测 | ✓ | ✓ | ✓ |
| Redis滑动窗口限流 | ✓ | ✓ | ✓ |
| KYC 实名认证 | - | ✓ | ✓ |
| 风控引擎 (4规则) | - | ✓ | ✓ |
| 支付回调验签 | - | - | ✓ |

---

## 国际化

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 多语言支持 | 中/英文 | 4语言 | 4语言 |
| 翻译表+缓存 | ✓ | ✓ | ✓ |
| 语言自动检测 | ✓ | ✓ | ✓ |
| 国家差异化配置 | - | - | ✓ 8国 |

---

## 部署运维

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| webman 独立部署 | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7服务 |
| Nginx 反向代理 | - | - | ✓ |
| CDN | - | - | ✓ 五厂商接入 + 管理端配置/启停/连通测试（凭据加密，service 纯 DB 读取） |
| Crontab 定时任务 | - | ✓ | ✓ |
| Prometheus 监控 | ✓ | ✓ | ✓ `/metrics` 业务 gauge + 事件 counter |
| 健康检查 | ✓ | ✓ | ✓ |
| hg/apidoc 在线文档 | - | - | ✓ 41控制器 |

---

## 客户端

| 功能 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| Flutter Web PC 管理后台 | ✓ 5页 | ✓ 11页 | ✓ 15页 |
| Flutter Web PC 用户平台 | ✓ 5页 | ✓ 8页 | ✓ 10页 |
| HarmonyOS admin | - | ✓ 登录+仪表盘 | ✓ 8页 `admin/apps/harmonyos/` |
| HarmonyOS C端 | - | - | ✓ 5页 `apps/harmonyos/` |

---

## 数据库表

### 基础版 (19张)
```
管理后台 (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

平台核心 (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### 标准版新增 (10张)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### 完整版新增 (13张)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## API 端点

| 模块 | 基础版 | 标准版 | 完整版 |
|------|--------|--------|--------|
| 认证 | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| 钱包 | 2 | 2 | 3 (+充值回调) |
| 兑换 | 4 | 4 | 4 |
| 提现 | 2 | 2 | 8 (+批量+限额+审核) |
| 游戏 | 3 | 4 | 7 (+区服+记录+搜索) |
| 用户 | 2 | 2 | 7 (+KYC+GDPR+隐私) |
| 管理后台 | 18 | 25 | 79 |
| 运营工具 | - | - | 30 (+排行榜+优惠券+通知+推荐) |
| 国际化 | 2 | 2 | 4 (+国家配置) |
| **总计** | **38** | **54** | **129** |

---

## 生态扩展 (v2.0) — 新增

| 功能 | 说明 |
|------|------|
| GameProvider 抽象层 | SelfProvider (DB事务) + ThirdPartyProvider (HTTP+签名) |
| Provider API 网关 | balance/bet/settle/refund 回调 + ProviderAuth 中间件 |
| 工单系统 | C端创建/回复 + 管理端处理/分配/关闭 |
| 邮箱验证 | 6位验证码、Redis 10分钟过期、60秒重发限制 |
| 推送通知 | PushService (FCM/APNs/华为推送) |
| VIP 体系 | 5级、经验值累计、自动升级、兑换折扣、提现减免、汇率加成 |
| 成就系统 | 12个内置成就、事件驱动检测、进度追踪 |
| 好友系统 | 申请/接受/拒绝/删除/搜索 |
| 私信/聊天 | REST + WebSocket 实时消息 (端口8790) |
| 事件总线 | Redis Pub/Sub；emit INCR `metrics:event_*`；消费进程 `EventConsumer` 已落地 |
| 特性开关 | FeatureFlag 基于DB；`inRollout`/`abTest` 读 `feature.{name}_percent` |
| Webhook | - | - | ✓ 7种事件+Pub/Sub投递 |
| 聊天 | - | - | ✓ REST+WebSocket :8791 |
| 赛事系统 | - | - | ✓ FeatureFlag+tournament |
| 优惠券条件 | - | - | ✓ min_deposit/first_user/game_id |
| 多级返佣 | - | - | ✓ 二级分润 |
| SDK文档 | - | - | ✓ PHP/Go/Python |
| 高级分析 | 留存/D1-D30、转化漏斗、ARPU/ARPPU |

### 新增数据表 (10张)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### 新增 Provider API 端点 (4个)
```
POST /api/provider/balance  — 查询余额
POST /api/provider/bet      — 通知下注
POST /api/provider/settle   — 通知结算
POST /api/provider/refund   — 通知退款
```

### 新增 C端 API 端点 (8个)
```
POST /api/verify/send-email    — 发送邮箱验证码
POST /api/verify/confirm-email — 确认邮箱
GET  /api/ticket/list             — 工单列表
POST /api/ticket/create           — 创建工单
GET  /api/ticket/{id}             — 工单详情
POST /api/ticket/{id}/reply       — 回复工单
GET  /api/user/vip-status         — VIP状态
GET  /api/user/achievements       — 成就列表
```

### 新增管理后台 API 端点 (6个)
```
GET  /admin/ticket/list          — 工单列表
GET  /admin/ticket/{id}          — 工单详情
POST /admin/ticket/{id}/reply    — 回复工单
POST /admin/ticket/{id}/close    — 关闭工单
POST /admin/ticket/{id}/assign   — 指定处理人
GET  /admin/analytics/retention  — 留存分析
GET  /admin/analytics/funnel     — 转化漏斗
GET  /admin/analytics/arpu       — ARPU趋势
GET  /admin/analytics/economy    — 经济指标
```
