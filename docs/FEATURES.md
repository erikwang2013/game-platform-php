# 功能文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 功能总览

### 基础版 (MVP) — 已完成

| 域 | 功能 | 状态 |
|----|------|------|
| 用户 | 注册/登录/JWT/验证码 | 已完成 |
| 钱包 | 平台币余额/流水查询 | 已完成 |
| 充值 | 创建充值订单（单一支付） | 已完成 |
| 兑换 | 平台币⇄游戏币（固定汇率+差价） | 已完成 |
| 提现 | 申请/查询/全局开关/自动审核/人工审核 | 已完成 |
| 游戏 | 后台CRUD/币种管理/C端列表/详情/启动 | 已完成 |
| 管理 | 游戏管理/提现审核/用户管理/支付管理/公告管理 | 已完成 |
| 面板 | 平台仪表盘（DAU/流水/收益/排行） | 已完成 |
| 导出 | Excel导出用户/流水/提现 | 已完成 |
| 国际化 | 中/英文切换、翻译表、语言检测中间件 | 已完成 |
| 前端 | Flutter PC管理后台 + C端用户平台（含i18n） | 已完成 |

### 标准版 — 已完成

| 域 | 功能 | 状态 |
|----|------|------|
| 用户 | OAuth登录 (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | 已完成 |
| 支付 | 多支付渠道自动回调 (Stripe/PayPal) | 已完成 |
| 游戏 | 区服管理、游戏记录追踪 | 已完成 |
| 提现 | KYC阶梯限额 (default/verified/vip) + 手续费 | 已完成 |
| KYC | 实名认证申请+审核 | 已完成 |
| 风控 | IP黑名单/大额预警/频率/速度检测 | 已完成 |
| 统计 | 日统计快照 (用户/充值/提现/兑换/游戏) | 已完成 |
| 前端 | Admin: KYC审核+风控日志 / Platform: OAuth+KYC+游戏记录 | 已完成 |

### 完整版 — 已完成

| 域 | 功能 | 状态 |
|----|------|------|
| 游戏大厅 | 10个预设分类、分类筛选、游戏-分类关联 | 已完成 |
| 排行榜 | 日榜/周榜/月榜/总榜、Redis缓存、多指标 | 已完成 |
| 优惠券 | 固定金额+比例折扣、限时限量、领取/使用追踪 | 已完成 |
| 国家配置 | 8国预设、差异化支付/提现方式、最低充值额 | 已完成 |
| 统计 | 日统计快照 + 平台收益追踪 | 已完成 |
| 搜索 | Elasticsearch 全文搜索（模型层已集成） | 已完成 |

### 生产级升级 — 已完成

| 域 | 功能 | 状态 |
|----|------|------|
| OAuth | Google/Facebook/Apple 真实token交换 | 已完成 |
| 支付 | Stripe/PayPal Webhook 签名验证 | 已完成 |
| 验证码 | poster-php 点击式验证码 | 已完成 |
| 通知 | 站内信 + 邮件、充值/提现/KYC/优惠券自动通知 | 已完成 |
| 2FA | Google Authenticator TOTP + 备用恢复码 | 已完成 |
| 推荐 | 推荐码、注册奖励、充值返佣 | 已完成 |
| 搜索 | ES 搜索API + 游戏建议 + LIKE回退 | 已完成 |
| 排行榜 | WebSocket 实时推送 (端口8789) | 已完成 |
| 部署 | Docker Compose 8服务 + Nginx反向代理 | 已完成 |
| 数据 | ClickHouse OLAP 分析 + 联合/条件概率计算 | 已完成 |
| HarmonyOS | 游戏大厅 + 钱包 + 游戏详情页 | 已完成 |
| API 文档 | hg/apidoc 交互式文档 | 已完成 |

### 生态扩展 (v2.0) — 刚完成

| 域 | 功能 | 状态 |
|----|------|------|
| 游戏接入 | GameProvider 抽象层 (Self/ThirdParty) + HMAC-SHA256 签名 | 已完成 |
| 游戏回调 | Provider API 网关 (balance/bet/settle/refund) + ProviderAuth 中间件 | 已完成 |
| 游戏会话 | Redis 心跳 + 15分钟超时自动结算 + GameSessionService | 已完成 |
| 工单系统 | C端创建/回复 + 管理端处理/分配/关闭、5种工单类型 | 已完成 |
| 邮箱验证 | 6位验证码、Redis 10分钟过期、60秒重发限制 | 已完成 |
| 推送通知 | PushService (FCM/APNs/华为推送) + DeviceToken 模型 | 已完成 |
| VIP 体系 | 5级 (普通/白银/黄金/铂金/钻石) + 经验值 + 自动升级 | 已完成 |
| VIP 权益 | 兑换折扣 2-15%、提现手续费减免 10-100%、汇率加成 0.1-1.0% | 已完成 |
| 成就系统 | 12个内置成就、事件驱动检测、进度追踪、经验值奖励 | 已完成 |
| 好友系统 | 申请/接受/拒绝/删除/搜索、pending/accepted/blocked 状态 | 已完成 |
| 私信/聊天 | REST 私信 + WebSocket 实时消息 (端口8790)、仅好友可发 | 已完成 |
| 事件总线 | Redis Pub/Sub 异步解耦、成就/VIP/通知/审计订阅 | 已完成 |
| 特性开关 | FeatureFlag 基于DB、零额外依赖、4个预设开关 | 已完成 |
| 高级分析 | 留存/D1-D30、转化漏斗、ARPU/ARPPU、游戏币种经济指标 (ClickHouse) | 已完成 |
| Webhook | 订阅管理 + Redis Pub/Sub 事件投递、7种事件可选 | 已完成 |
| 聊天 | REST 私信 + WebSocket 实时消息 (端口8791)、仅好友可发 | 已完成 |
| 赛事 | 创建/list/detail/join、FeatureFlag开关、排行榜、人数上限 | 已完成 |
| 多级返佣 | 二级推荐分润、ReferralCommission 模型、可配置佣金率 | 已完成 |
| 优惠券条件 | min_deposit/first_user_only/game_id 三种条件限制 | 已完成 |
| SDK 文档 | Provider 接入文档 (PHP/Go/Python 示例 + 4 API 端点) | 已完成 |

## 2. C端用户功能

### 2.1 用户旅程

```
注册 → 登录 → 邮箱/手机验证 → 浏览游戏大厅 → 进入游戏详情
                                           ↓
查看钱包 ← 玩游戏 ← 兑换游戏币 (VIP折扣) ← 充值平台币
    ↓
  提现 (VIP手续费减免) → 后台审核 → 到账
    ↓
好友系统 → 私信聊天 → 排行榜竞技 → 成就追踪
    ↓
工单支持
```

### 2.2 API 接口

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /api/auth/register | 用户注册 | 否 |
| POST | /api/auth/login | 用户登录 | 否 |
| POST | /api/auth/refresh | 刷新Token | 否 |
| GET | /api/game/list | 游戏列表 | 否 |
| GET | /api/game/detail/{id} | 游戏详情 | 否 |
| GET | /api/announcement/list | 公告列表 | 否 |
| GET | /api/wallet/info | 钱包余额 | 是 |
| GET | /api/wallet/transactions | 流水记录 | 是 |
| POST | /api/deposit/create | 创建充值订单 | 是 |
| POST | /api/exchange/quote | 兑换询价 (VIP折扣) | 是 |
| POST | /api/exchange/buy | 买入游戏币 | 是 |
| POST | /api/exchange/sell | 卖出游戏币 | 是 |
| POST | /api/withdraw/apply | 提现申请 (VIP减免) | 是 |
| POST | /api/game/launch | 启动游戏 | 是 |
| GET | /api/game/play-logs | 游戏记录 | 是 |
| POST | /api/referral/apply | 使用推荐码 | 是 |
| POST | /api/verify/send-email | 发送邮箱验证码 | 是 |
| POST | /api/verify/confirm-email | 确认邮箱 | 是 |
| GET | /api/ticket/list | 工单列表 | 是 |
| POST | /api/ticket/create | 创建工单 | 是 |
| POST | /api/ticket/{id}/reply | 回复工单 | 是 |

## 3. 管理后台功能

### 3.1 API 接口（新增）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/dashboard/platform | 平台仪表盘数据 |
| GET | /admin/analytics/overview | 平台概览 (ClickHouse) |
| GET | /admin/analytics/retention | 留存分析 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | 转化漏斗 |
| GET | /admin/analytics/arpu | ARPU/ARPPU 趋势 |
| GET | /admin/analytics/economy | 游戏币种经济指标 |
| GET | /admin/analytics/probability | 联合概率分析 |
| GET | /admin/game/list | 游戏列表 |
| POST | /admin/game/create | 创建游戏 (含 provider_config) |
| PUT | /admin/game/{id} | 编辑游戏 |
| GET | /admin/withdraw/orders | 提现订单列表 |
| PUT | /admin/withdraw/review | 审核提现 |
| GET | /admin/ticket/list | 工单列表 |
| GET | /admin/ticket/{id} | 工单详情 |
| POST | /admin/ticket/{id}/reply | 回复工单 |
| POST | /admin/ticket/{id}/close | 关闭工单 |
| POST | /admin/ticket/{id}/assign | 指定处理人 |

## 4. Provider API（游戏方回调）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /api/provider/balance | 查询用户余额 | HMAC-SHA256 |
| POST | /api/provider/bet | 通知下注 | HMAC-SHA256 |
| POST | /api/provider/settle | 通知结算 | HMAC-SHA256 |
| POST | /api/provider/refund | 通知退款 | HMAC-SHA256 |

签名算法: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
请求头: `X-Game-Id` + `X-Timestamp` + `X-Signature`
时间窗口: 5分钟

## 5. VIP 体系

| 等级 | 累计EXP | 兑换折扣 | 提现手续费减免 | 汇率加成 |
|------|---------|---------|-------------|---------|
| 普通 | 0 | 0% | 0% | 基准 |
| 白银 | 500 | 2% | 10% | +0.1% |
| 黄金 | 2,500 | 5% | 30% | +0.3% |
| 铂金 | 12,500 | 10% | 50% | +0.5% |
| 钻石 | 62,500 | 15% | 100% | +1.0% |

### 经验值获取

| 行为 | EXP |
|------|-----|
| 充值 1 元 | 10 |
| 每日登录 | 5 |
| 完成 KYC | 50 |
| 邀请新用户 | 100 |
| 达成成就 | 10-100 |

## 6. 成就清单

| 成就 | 条件 | 积分 |
|------|------|------|
| First Deposit | 首次充值 | 20 |
| Century Club | 累计充值100 | 50 |
| High Roller | 累计充值1000 | 100 |
| Trader | 首次兑换 | 20 |
| Day Trader | 累计兑换100次 | 100 |
| Explorer | 玩过3款游戏 | 30 |
| Adventurer | 玩过5款游戏 | 50 |
| Conqueror | 玩过10款游戏 | 100 |
| Weekly Warrior | 连续7天登录 | 30 |
| Monthly Master | 连续30天登录 | 100 |
| Connector | 邀请1个好友 | 30 |
| Influencer | 邀请10个好友 | 100 |

## 7. 数据库表清单

### 生态扩展新增 (10张)

| 表名 | 说明 | 关键特性 |
|------|------|---------|
| erik_ticket | 工单 | user_id+type+status 索引, assigned_to |
| erik_ticket_reply | 工单回复 | ticket_id 索引, is_admin 区分 |
| erik_device_token | 设备令牌 | user_id+platform+token 唯一索引 |
| erik_vip_level | VIP等级定义 | level 唯一索引, benefits JSON |
| erik_user_vip | 用户VIP记录 | user_id 唯一索引, level+exp+total_exp |
| erik_exp_log | 经验值日志 | user_id+source 组合索引 |
| erik_achievement | 成就定义 | key 唯一索引, condition_json JSON |
| erik_user_achievement | 用户成就 | user_id+achievement_id 唯一索引 |
| erik_friend | 好友关系 | user_id+friend_id 唯一索引 |
| erik_message | 私信 | from_user_id+to_user_id / to_user_id+is_read |

### 表结构变更

| 表名 | 变更 |
|------|------|
| erik_game | +provider_config (JSON) |
| erik_game_play_log | +round_id, +bet_amount, +win_amount |

**总计: 52 张表** (原有 42 + 新增 10)

## 8. 测试覆盖

| 测试文件 | 用例数 | 覆盖范围 |
|---------|--------|---------|
| PlatformTest | 56 | bcmath精度/兑换计算/提现费用/限额/风控/优惠券/KYC/i18n |
| BackendEnhancementTest | 23 | 加密服务/Hashids/Snowflake |
| CaptchaTest | 7 | 验证码生成/校验 |
| EncryptionServiceTest | 6 | AES加解密/脱敏 |
| EnvConfigTest | 4 | 环境变量配置 |
| HashidsServiceTest | 8 | ID编解码往返 |
| SnowflakeServiceTest | 6 | ID生成唯一性 |

**总计: 116 测试**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
