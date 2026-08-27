# 功能设计文档
<!-- lang-nav -->

Languages: **中文** · [English](FEATURE-DESIGN.en.md) · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 币种体系设计

### 1.1 三层币种模型

```
第1层: 法币 (USD / CNY / EUR / JPY ...)
       ↕ 充值/提现（按汇率兑换）
第2层: 平台币（统一，精度 decimal(18,4)）
       ↕ 兑换（含汇率 + 平台抽成差价）
第3层: 游戏币（每种游戏独立，独立汇率）
```

### 1.2 平台币

- 平台内统一计价单位
- 精度：`DECIMAL(18,4)`，最小单位 0.0001
- 通过法币充值获得，可兑换为任意游戏币
- 游戏币也可兑回平台币，再提现为法币
- 平台收取兑换差价作为收入来源

### 1.3 游戏币

- 每种游戏可有多个游戏币种（如金币、钻石、积分）
- 每个币种独立设置对平台币的兑换汇率 (`exchange_rate`)
- 每个币种独立设置平台抽成比例 (`spread_pct`)
- 支持设置最小/最大兑换限额 (`min_exchange` / `max_exchange`)

### 1.4 兑换公式

**买入游戏币（平台币 → 游戏币）：**
```
游戏币到账 = 平台币数量 × exchange_rate × (1 - spread_pct / 100)
```

**卖出游戏币（游戏币 → 平台币）：**
```
平台币到账 = 游戏币数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**示例：**
- exchange_rate = 100（1平台币 = 100游戏币）
- spread_pct = 5%（平台抽5%差价）
- 用户用 10 平台币买入：(10 × 100 × 0.95) = 950 游戏币
- 用户卖出 950 游戏币：(950 ÷ 100 × 0.95) = 9.025 平台币
- 平台收益：10 - 9.025 = 0.975 平台币

## 2. 钱包设计

### 2.1 平台币钱包 (game_user_wallet)

用户注册时自动创建，余额初始为 0。

| 字段 | 说明 |
|------|------|
| balance | 可用余额（可充提可兑换） |
| frozen_balance | 冻结余额（预留，如提现中） |
| total_earned | 累计收入 |
| total_spent | 累计支出 |
| version | 乐观锁版本号（每次更新+1） |

### 2.2 游戏币钱包 (game_user_game_wallet)

按用户+游戏+币种三维唯一。首次兑换时自动创建。

### 2.3 并发安全

使用乐观锁防止并发问题：

```php
// 更新时检查版本号
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 更新失败（版本号已变）→ 重试，最多5次
```

## 3. 提现系统设计

### 3.1 多层控制

```
第1层: 全局提现开关
       ├─ 关闭 → 所有提现拒绝，用于紧急风控
       └─ 开启 → 进入第2层检查

第2层: 限额检查
       ├─ 单笔最低金额 (min_amount)
       ├─ 单笔最高金额 (max_amount)
       └─ 每日累计限额 (daily_limit)

第3层: 审核流程
       ├─ 金额 < 自动审核阈值 → 自动通过
       └─ 金额 >= 自动审核阈值 → 人工审核 → 通过/拒绝
```

### 3.2 提现状态机

```
pending (待审核)
  ├─→ approved (已通过) → completed (已完成)
  └─→ rejected (已拒绝) → 余额退回 + 退款流水
```

### 3.3 管理后台控制

- **全局开关按钮**：一键开启/关闭所有用户提现
- **审核队列**：按时间排序的待审核列表，通过/拒绝按钮
- **限额配置**：可视化设置各限额参数

## 4. 充值设计

### 4.1 充值流程

```
1. 用户选择支付方式和金额
2. 平台创建充值订单 (status=pending, 生成唯一 order_no)
3. 跳转第三方支付页面
4. 用户完成支付
5. 第三方回调通知平台 (POST /api/payment/callback)
6. 平台验签 → 更新订单 (status=confirmed)
7. 平台币到账 → 记录流水
```

### 4.2 支付方式

| 类型 | 提供商 | 说明 |
|------|--------|------|
| 法币 | Stripe | 国际信用卡支付 |
| 法币 | PayPal | 全球电子钱包 |
| 法币 | Alipay | 支付宝（中国大陆） |
| 法币 | WeChat Pay | 微信支付（中国大陆） |
| 加密货币 | USDT-TRC20 | 波场链 USDT |

基础版先对接单一支付方式（如 Stripe），标准版扩展全部渠道。

## 5. 游戏集成设计

### 5.1 自研游戏

自研游戏直接集成到平台，共享用户体系和钱包：

- 游戏通过内部 API 查询用户游戏币余额
- 游戏结算通过内部 API 扣减/增加游戏币
- 无需额外的签名验签

### 5.2 第三方游戏

第三方游戏通过 SDK/API 对接：

```
平台侧:
  1. 用户点击"进入游戏"
  2. 平台生成签名（user_id + timestamp + api_secret → HMAC-SHA256）
  3. 302跳转或iframe加载游戏URL（携带签名参数）

游戏侧:
  4. 验签 → 建立游戏会话
  5. 查询余额：GET /api/game/balance?user_id=...&sign=...
  6. 结算回调：POST /api/game/callback {user_id, amount, type, sign}
  7. 平台验签 → 更新余额 → 记录流水 → 返回结果
```

### 5.3 签名算法

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

验证条件：
- 签名正确
- 时间戳在 ±60s 内（防 replay attack）
- nonce 未使用过（Redis 记录，60s 过期）
- 请求 IP 在白名单内

## 6. 权限设计

### 6.1 角色预设

| 角色 | 权限范围 |
|------|---------|
| 超级管理员 | * (所有权限) |
| 游戏运营 | 游戏管理、公告管理、仪表盘 |
| 财务审核 | 提现审核、支付管理、流水查看 |
| 客服 | C端用户查看、充值订单查看 |

### 6.2 权限粒度

```
{method}.{path}

示例:
  get.admin/game/list      → 查看游戏列表
  post.admin/game/create   → 创建游戏
  put.admin/withdraw/review → 审核提现
  put.admin/withdraw/switch → 操作提现开关（仅超级管理员）
```

## 呼. 标准版新增设计

### 8.1 风控引擎

四种规则类型：
- `ip_blacklist` — IP 黑名单匹配，命中直接阻断
- `amount_anomaly` — 单笔大额检测，超过阈值发出警告
- `frequency` — 时间窗口内操作频率检测
- `velocity` — 短时多账号关联检测

规则按 priority 降序执行，首个匹配的规则决定结果（block > warn > log）。

### 8.2 OAuth 第三方登录

支持的提供商：Google、Facebook、Apple

流程：
1. 前端请求 `GET /api/auth/oauth/{provider}` 获取授权 URL
2. 用户跳转第三方完成授权
3. 回调 `POST /api/auth/oauth/{provider}/callback` 携带授权码
4. 后端查找已有绑定 → 直接登录；无绑定 → 自动注册+绑定+创建钱包

### 8.3 KYC 限额体系

| 等级 | 获取方式 | 单笔上限 | 日限额 | 手续费 |
|------|---------|---------|--------|--------|
| default | 注册默认 | 1,000 | 10,000 | 1.00% |
| verified | KYC审核通过 | 5,000 | 50,000 | 0.50% |
| vip | 运营授予 | 20,000 | 200,000 | 0.00% |

### 8.4 游戏区服

每个游戏可配置多个区服（region: global/asia/eu/na），区服状态：维护/正常/火爆/新服。

### 8.5 日统计快照

每日凌晨 crontab 执行 `ComputeDailyStats::run()`，计算五项指标：
- 用户统计（新增/活跃/累计）
- 充值统计（笔数/总金额）
- 提现统计（笔数/总金额）
- 兑换统计（笔数/手续费总额）
- 游戏统计（玩家数/会话数）

## 9. 生产级功能

### 9.1 通知系统

通知类型：system/deposit/withdraw/kyc/coupon/announcement

自动触发场景：
- 充值到账 → NotificationService::send()
- 提现审核通过/拒绝 → 自动通知
- KYC 审核通过/拒绝 → 自动通知
- 优惠券领取 → 自动通知
- 推荐奖励到账 → 自动通知

支持站内信 + 邮件双通道（邮件需配置 MAIL_HOST 环境变量）。

### 9.2 推荐返利

```
用户A 生成推荐码 → 分享给用户B
用户B 注册时填写推荐码 → 双方各得注册奖励(signup_reward)
用户B 充值 → A 获得充值返佣(deposit_commission_pct%)
```

### 9.3 2FA 双因素认证

- TOTP 标准协议 (RFC 6238)，兼容 Google Authenticator
- 启用流程：获取密钥 → 扫码绑定 → 验证TOTP → 生成8个备用恢复码
- 登录二次验证：POST /api/2fa/verify
- 支持 ±1 时间窗口容差 (30秒)

### 9.4 真实 OAuth 对接

| 提供商 | Token端点 | 用户信息端点 |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | JWT id_token 解码 |

配置通过 PlatformConfig 或环境变量，请求失败自动回退 mock 模式。

### 9.5 支付 Webhook 验签

- Stripe: HMAC-SHA256 签名验证 (Stripe-Signature 头)
- PayPal: POST 回 PayPal 验证端点
- 未配置密钥时自动跳过验证（开发模式）

### 9.6 WebSocket 实时排行榜

- 协议：WebSocket (ws://host:8789)
- 订阅：{action: "subscribe", leaderboard_id: 123}
- 推送：{type: "ranking_update", rankings: [...]}
- 支持 ping/pong 心跳保活

## 7. 国际化设计

### 7.1 支持语言

| 代码 | 名称 | 本地语 | 图标 |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 翻译管理

- 翻译以 `group.key` 格式组织（如 `auth.login_success`）
- 数据库表 `game_translation` 存储，Redis 缓存（TTL 1小时）
- API: `GET /api/language/list` 获取可用语言，`POST /api/language/switch` 切换语言
- 前端通过 `X-Language` 请求头或 `Accept-Language` 自动检测
- 翻译缺失时回退到 en-US，en-US 也无则返回原始 key

### 7.3 用户语言偏好

- 用户注册时根据浏览器 `Accept-Language` 自动设置
- 登录后可通过 `PUT /api/user/profile` 修改 `language` 字段
- 切换语言时同步更新用户记录

## 8. 平台收益模型

| 收益来源 | 计算方式 | 说明 |
|---------|---------|------|
| 兑换差价 | 每笔兑换的 spread_fee | 买入卖出双向收取 |
| 提现手续费 | 提现金额 × fee_pct | 标准版实现 |
| 游戏分成 | 第三方游戏收入分成 | 按合同约定 |
| 充值汇差 | 法币→平台币汇率差 | 平台设定汇率与市场汇率差值 |
