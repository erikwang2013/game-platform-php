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

### 标准版 — 规划中

| 域 | 功能 |
|----|------|
| 用户 | OAuth登录 (Google/Facebook/Apple) |
| 支付 | 多支付渠道自动回调 |
| 游戏 | 第三方游戏SDK（签名验签/回调结算） |
| 提现 | 自动打款对接、阶梯手续费 |
| KYC | 实名认证申请+审核 |
| 风控 | 基础风控规则引擎 |
| 前端 | HarmonyOS客户端 |
| 数据 | 仪表盘可视化图表、Excel/PDF完整导出 |

### 完整版 — 规划中

| 域 | 功能 |
|----|------|
| 国际化 | 多语言（i18n）、多币种、国家差异化配置 |
| 运营 | 排行榜、优惠券系统、公告定时发布 |
| 搜索 | Elasticsearch 全文搜索 |
| 风控 | 完整规则引擎（刷分/多账号/异常频率） |
| 统计 | 日统计快照、平台收益分析 |

## 2. C端用户功能

### 2.1 用户旅程

```
注册 → 登录 → 浏览游戏大厅 → 进入游戏详情
                                    ↓
查看钱包 ← 玩游戏 ← 兑换游戏币 ← 充值平台币
    ↓
  提现 → 后台审核 → 到账
```

### 2.2 页面清单

| 页面 | 说明 | 路由 |
|------|------|------|
| 登录/注册 | 用户名+密码，JWT认证 | /login |
| 游戏大厅 | 游戏卡片网格、搜索、分类 | /games |
| 游戏详情 | 游戏信息、币种汇率、启动入口 | /game-detail |
| 我的钱包 | 余额、流水记录、快捷操作 | /wallet |
| 充值 | 选择支付方式、输入金额、下单 | /deposit |
| 兑换 | 选择游戏→询价→确认兑换 | /exchange |
| 提现 | 输入金额→选择方式→填写账户→提交 | /withdraw |
| 个人中心 | 资料编辑、语言设置、退出 | /profile |

### 2.3 API 接口

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /api/auth/register | 用户注册 | 否 |
| POST | /api/auth/login | 用户登录 | 否 |
| POST | /api/auth/refresh | 刷新Token | 否 |
| GET | /api/language/list | 可用语言列表 | 否 |
| POST | /api/language/switch | 切换语言 | 否 |
| GET | /api/game/list | 游戏列表 | 否 |
| GET | /api/game/{id} | 游戏详情 | 否 |
| GET | /api/announcement/list | 公告列表 | 否 |
| GET | /api/announcement/{id} | 公告详情 | 否 |
| GET | /api/wallet/info | 钱包余额 | 是 |
| GET | /api/wallet/transactions | 流水记录 | 是 |
| POST | /api/deposit/create | 创建充值订单 | 是 |
| GET | /api/deposit/orders | 充值记录 | 是 |
| POST | /api/exchange/quote | 兑换询价 | 是 |
| POST | /api/exchange/buy | 买入游戏币 | 是 |
| POST | /api/exchange/sell | 卖出游戏币 | 是 |
| GET | /api/exchange/records | 兑换记录 | 是 |
| POST | /api/withdraw/apply | 提现申请 | 是 |
| GET | /api/withdraw/orders | 提现记录 | 是 |
| POST | /api/game/launch | 启动游戏 | 是 |
| GET | /api/user/profile | 个人信息 | 是 |
| PUT | /api/user/profile | 编辑资料 | 是 |

## 3. 管理后台功能

### 3.1 页面清单

| 页面 | 说明 | 路由 |
|------|------|------|
| 仪表盘 | 管理统计 + 平台统计 | /dashboard |
| 管理员用户 | 后台用户CRUD | /users |
| 角色权限 | RBAC角色和权限管理 | /roles |
| 系统配置 | 键值对系统配置 | /config |
| 操作日志 | 操作审计日志 | /logs |
| 游戏管理 | 游戏CRUD + 币种汇率管理 | /games |
| 提现管理 | 审核队列 + 全局开关 + 限额配置 | /withdraws |
| 平台用户 | C端用户列表/详情/封禁 | /platform-users |
| 支付管理 | 支付方式启禁用 | /payments |
| 公告管理 | 公告发布/编辑 | /announcements |
| 个人中心 | 修改密码/退出登录 | /profile |

### 3.2 API 接口（新增）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /admin/dashboard/platform | 平台仪表盘数据 |
| GET | /admin/game/list | 游戏列表 |
| POST | /admin/game/create | 创建游戏 |
| PUT | /admin/game/{id} | 编辑游戏 |
| DELETE | /admin/game/{id} | 删除游戏 |
| POST | /admin/game/currency/manage | 管理游戏币种 |
| GET | /admin/withdraw/orders | 提现订单列表 |
| PUT | /admin/withdraw/review | 审核提现 |
| PUT | /admin/withdraw/switch | 全局提现开关 |
| POST | /admin/withdraw/limits/set | 设置提现限额 |
| GET | /admin/platform/user/list | C端用户列表 |
| GET | /admin/platform/user/{id} | C端用户详情 |
| PUT | /admin/platform/user/{id} | 编辑/封禁用户 |
| GET | /admin/payment/method/list | 支付方式列表 |
| POST | /admin/payment/method/toggle | 启禁用支付方式 |
| GET | /admin/announcement/list | 公告列表 |
| POST | /admin/announcement/create | 发布公告 |
| POST | /admin/export/users | 导出C端用户Excel |
| POST | /admin/export/transactions | 导出流水Excel |

## 4. 数据库表清单

### 基础版 (12张)

| 表名 | 说明 | 关键特性 |
|------|------|---------|
| erik_user | C端用户 | 软删除、邮箱/手机加密 |
| erik_user_wallet | 平台币钱包 | 乐观锁、DECIMAL(18,4) |
| erik_user_game_wallet | 游戏币钱包 | 用户+游戏+币种唯一索引 |
| erik_game | 游戏 | API密钥加密存储 |
| erik_game_currency | 游戏币种 | 汇率+抽成+限额 |
| erik_deposit_order | 充值订单 | 订单号唯一 |
| erik_withdraw_order | 提现订单 | 收款信息加密、审核人追溯 |
| erik_exchange_record | 兑换记录 | 双向记录、手续费 |
| erik_transaction | 平台流水 | 关联单据、余额快照 |
| erik_payment_method | 支付方式 | 配置加密、启禁用 |
| erik_announcement | 公告 | 定时发布、按语言过滤 |
| erik_platform_config | 平台配置 | 类型化存取、分组管理 |
| erik_language | 语言定义 | 4种语言预设 |
| erik_translation | 翻译文本 | group.key + lang_code 唯一索引 |

（admin 原有 7 张表：erik_admin_user, erik_admin_role, erik_admin_permission, erik_admin_user_role, erik_admin_role_permission, erik_operation_log, erik_system_config）

### 标准版新增 (10张)

erik_user_identity, erik_user_oauth, erik_user_payment_account, erik_user_session, erik_game_server, erik_game_play_log, erik_withdraw_limit, erik_risk_rule, erik_risk_log, erik_stat_daily

### 完整版新增 (8张)

erik_game_category, erik_game_category_rel, erik_leaderboard, erik_coupon, erik_user_coupon, erik_language, erik_translation, erik_country_config, erik_platform_revenue
