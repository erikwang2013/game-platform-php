# 接口文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

在线交互式文档（支持在线调试）：
- C端业务：http://localhost:8788/apidoc/
- 管理后台：http://localhost:8787/apidoc/
- 密码：admin123

## 1. 约定

### 1.1 基础 URL

| 端 | 地址 |
|----|------|
| 管理后台 | `http://localhost:8787` |
| C端业务 | `http://localhost:8788` |

### 1.2 通用请求头

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (需要认证的接口)
```

### 1.3 统一响应格式

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 含义 |
|------|------|
| 0 | 成功 |
| 400 | 参数错误 |
| 401 | 未认证（Token缺失/过期/无效） |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 422 | 验证失败 |
| 429 | 请求过于频繁（触发限流） |
| 500 | 服务端错误 |

### 1.4 ID 编码

所有接口请求和响应中的 ID 均为 Hashids 编码字符串，非原始 BIGINT 值。

```
外部: aB3xK9mW2pQ7rT5v  (hashid 字符串)
内部: 1750123456789      (Snowflake BIGINT)
```

### 1.5 分页格式

```
请求: ?page=1&per_page=20

响应: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C端接口 (service :8788)

### 2.1 认证

#### POST /api/auth/register — 用户注册

```
请求: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": {
    "id": "aB3xK9mW2pQ7rT5v",
    "username": "player1",
    "nickname": "",
    "avatar": ""
  }
}
```

#### POST /api/auth/login — 用户登录

```
请求: {
  "username": "player1",
  "password": "123456"
}

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

错误: 401 用户名或密码错误 / 账号已被禁用

#### POST /api/auth/refresh — 刷新Token

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 钱包

#### GET /api/wallet/info — 钱包信息

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — 流水记录

```
需认证: 是
参数: ?page=1&per_page=20&type=deposit    (type 可选)

响应: {
  "list": [
    {
      "id": "...",
      "type": "deposit",
      "amount": "100.0000",
      "balance_after": "100.5000",
      "remark": "充值到账",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 25,
  "page": 1,
  "per_page": 20
}

type 可选值: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 充值

#### POST /api/deposit/create — 创建充值订单

```
需认证: 是

请求: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

响应: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000"
}
```

currency 可选值: USD / CNY / EUR

#### GET /api/deposit/orders — 充值记录

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
  "list": [
    {
      "id": "...",
      "order_no": "DEP...",
      "amount": "10.00",
      "currency": "USD",
      "platform_amount": "10.0000",
      "status": "pending",
      "paid_at": null,
      "created_at": "2026-05-22 10:25:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

status 可选值: pending / paid / confirmed / cancelled

### 2.4 兑换

#### POST /api/exchange/quote — 询价

```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

响应: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=买入游戏币 / out=卖出游戏币

#### POST /api/exchange/buy — 买入游戏币

```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

响应: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

错误: 422 平台币余额不足 / 404 游戏不可用

#### POST /api/exchange/sell — 卖出游戏币

```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

响应: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

错误: 422 游戏币余额不足

#### GET /api/exchange/records — 兑换记录

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "direction": "in",
      "platform_amount": "10.0000",
      "game_amount": "950.0000",
      "rate": "100.00000000",
      "spread_fee": "50.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20
}
```

### 2.5 提现

#### POST /api/withdraw/apply — 提现申请

```
需认证: 是

请求: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

响应: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method 可选值: paypal / bank / crypto

status:
- approved: 自动通过（金额 < auto_approve_threshold）
- pending: 待审核（金额 >= auto_approve_threshold）

错误:
- 403 提现功能暂时关闭（全局开关关闭）
- 400 低于最低提现金额
- 400 超过每日提现限额
- 400 余额不足

#### GET /api/withdraw/orders — 提现记录

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "platform_amount": "50.0000",
      "method": "paypal",
      "status": "pending",
      "review_note": "",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3,
  "page": 1,
  "per_page": 20
}
```

### 2.6 游戏

#### GET /api/game/list — 游戏列表

```
参数: ?page=1&per_page=20&keyword=射击&type=self

响应: {
  "list": [
    {
      "id": "aB3xK...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "description": "一款精彩的射击游戏",
      "cover_image": "https://...",
      "currencies": [
        {
          "id": "...",
          "name": "金币",
          "symbol": "G",
          "exchange_rate": "100.00000000",
          "min_exchange": "1.0000",
          "max_exchange": "10000.0000"
        }
      ]
    }
  ],
  "total": 20,
  "page": 1,
  "per_page": 20
}
```

type 可选值: self / third_party

#### GET /api/game/{hashid} — 游戏详情

```
响应: {
  "id": "...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "description": "...",
  "cover_image": "https://...",
  "currencies": [
    {
      "id": "...",
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}
```

#### POST /api/game/launch — 启动游戏

```
需认证: 是

请求: { "game_id": "aB3xK..." }

响应: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth 第三方登录

支持 7 个平台: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — 获取授权URL

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth回调

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=新注册用户 / false=已有账号绑定

### 2.8 KYC 实名认证

#### GET /api/user/identity/status — 认证状态

```
需认证: 是

响应: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — 提交认证

```
需认证: 是

请求: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

响应: { "message": "KYC submitted successfully" }
```

### 2.9 支付

#### POST /api/payment/callback — 支付回调（公开）

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

#### GET /api/payment/methods — 可用支付方式（公开）

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "status": 1 }
  ]
}
```

### 2.10 游戏记录

#### GET /api/game/play-logs — 游戏记录列表

```
需认证: 是
参数: ?page=1&per_page=20&game_id=xxx&action=start

响应: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "action": "start",
      "game_amount_change": "-10.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 50, "page": 1, "per_page": 20
}
```

#### GET /api/game/play-log/{hashid} — 游戏记录详情

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 排行榜

#### GET /api/leaderboard/list — 排行榜列表

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — 排行榜详情

```
响应: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 优惠券

#### GET /api/coupon/available — 可领优惠券

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — 领取优惠券

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — 我的优惠券

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 国家配置

#### GET /api/country/list — 国家列表

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — 国家详情

```
响应: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 通知

#### GET /api/notification/list — 通知列表

```
需认证: 是
参数: ?page=1&per_page=20&is_read=0

响应: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — 未读数量

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — 标记已读

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 推荐

#### GET /api/referral/my-code — 我的推荐码

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — 使用推荐码

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA状态

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — 设置2FA

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — 启用2FA

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — 验证2FA（公开）

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 搜索

#### GET /api/search — 全局搜索

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — 搜索建议

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 语言

#### GET /api/language/list — 可用语言列表

```
响应: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — 切换语言

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

locale 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.8 用户

#### GET /api/user/profile — 个人信息

```
需认证: 是

响应: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "avatar": "https://...",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /api/user/profile — 编辑资料

```
需认证: 是

请求: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

响应: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.9 公告

#### GET /api/announcement/list — 公告列表

```
响应: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "created_at": "2026-05-22 09:00:00"
    }
  ]
}
```

#### GET /api/announcement/detail/{hashid} — 公告详情

```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. 管理后台接口 (admin :8787)

### 3.1 平台仪表盘

#### GET /admin/dashboard/platform

```
需认证: 是 (AdminAuth + AdminPermission)

响应: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 游戏管理

#### GET /admin/game/list — 游戏列表

```
需认证: 是
参数: ?page=1&per_page=20&keyword=射击

响应: {
  "list": [
    {
      "id": "...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "status": 1,
      "sort": 0,
      "currency_count": 2,
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 12,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/game/create — 创建游戏

```
需认证: 是

请求: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // 可选
  "cover_image": "https://...",    // 可选
  "api_endpoint": "https://...",   // 可选
  "api_key": "...",                // 可选
  "api_secret": "...",             // 可选
  "status": 1,                     // 可选, 默认0
  "sort": 0                        // 可选, 默认0
}

响应: { "id": "aB3xK..." }
```

type 可选值: self / third_party

#### PUT /admin/game/{hashid} — 编辑游戏

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — 删除游戏

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — 管理币种

```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // 空=新建, 有值=更新
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

响应: { "message": "币种更新成功" }
```

### 3.3 提现管理

#### GET /admin/withdraw/orders — 提现订单列表

```
需认证: 是
参数: ?page=1&per_page=20&status=pending

响应: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "user": {
        "id": "...",
        "username": "player1"
      },
      "platform_amount": "500.0000",
      "method": "paypal",
      "status": "pending",
      "reviewer_id": null,
      "review_note": "",
      "reviewed_at": null,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### PUT /admin/withdraw/review — 审核提现

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=通过 / reject=拒绝（拒绝时自动退回平台币）

错误: 422 订单状态不是待审核

#### PUT /admin/withdraw/switch — 全局提现开关

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — 设置提现限额

```
需认证: 是

请求: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

响应: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 平台用户管理

#### GET /admin/platform/user/list — C端用户列表

```
需认证: 是
参数: ?page=1&per_page=20&keyword=player&status=1

响应: {
  "list": [
    {
      "id": "...",
      "username": "player1",
      "nickname": "Player One",
      "country": "US",
      "status": 1,
      "last_login_at": "2026-05-22 10:00:00",
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 1500,
  "page": 1,
  "per_page": 20
}
```

#### GET /admin/platform/user/{hashid} — 用户详情

```
需认证: 是

响应: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "status": 1,
  "wallet": {
    "balance": "100.5000",
    "frozen_balance": "0.0000"
  },
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /admin/platform/user/{hashid} — 编辑/封禁用户

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 支付管理

#### GET /admin/payment/method/list

```
需认证: 是

响应: {
  "list": [
    {
      "id": "...",
      "name": "Stripe",
      "type": "fiat",
      "provider": "stripe",
      "status": 1
    }
  ]
}
```

#### POST /admin/payment/method/toggle — 启禁用支付方式

```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 公告管理

#### GET /admin/announcement/list

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "status": 1,
      "start_at": "2026-05-23 02:00:00",
      "end_at": "2026-05-23 04:00:00",
      "created_at": "2026-05-22 09:00:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

#### POST /admin/announcement/create — 发布公告

```
需认证: 是

请求: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

响应: { "id": "aB3xK..." }
```

### 3.7 KYC 审核

#### GET /admin/identity/list — KYC列表

```
需认证: 是
参数: ?page=1&per_page=20&status=pending

响应: {
  "list": [
    {
      "id": "...",
      "user": { "id": "...", "username": "player1" },
      "real_name": "J***",
      "id_type": "id_card",
      "status": "pending",
      "created_at": "2026-05-22 10:00:00"
    }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### PUT /admin/identity/review — 审核KYC

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 游戏区服管理

#### GET /admin/game/server/list — 区服列表

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — 创建区服

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — 编辑区服

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — 删除区服

```
需认证: 是
```

### 3.9 提现阶梯限额管理

#### GET /admin/withdraw/limits/list

```
需认证: 是

响应: {
  "list": [
    {
      "id": "...",
      "user_level": "verified",
      "single_min": "1.0000",
      "single_max": "5000.0000",
      "daily_limit": "50000.0000",
      "monthly_limit": "200000.0000",
      "fee_pct": "0.50",
      "fee_max": "25.0000",
      "auto_approve_threshold": "500.0000"
    }
  ]
}
```

#### PUT /admin/withdraw/limits/{hashid} — 更新限额

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 游戏分类管理

#### GET /admin/game/category/list

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
需认证: 是
请求: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — 编辑分类

#### DELETE /admin/game/category/{hashid} — 删除分类

#### POST /admin/game/category/assign — 分配游戏

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 排行榜管理

#### GET /admin/leaderboard/list — 排行榜列表

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — 创建排行榜

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — 编辑排行榜

#### DELETE /admin/leaderboard/{hashid} — 删除排行榜

#### POST /admin/leaderboard/{hashid}/refresh — 刷新缓存

### 3.13 优惠券管理

#### GET /admin/coupon/list — 优惠券列表

#### POST /admin/coupon/create — 创建优惠券

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — 编辑（未领取时）

#### DELETE /admin/coupon/{hashid} — 删除

#### GET /admin/coupon/{hashid}/stats — 领取统计

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 国家配置管理

#### GET /admin/country/config/list — 国家配置列表

#### POST /admin/country/config/create — 创建国家配置

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — 编辑国家配置

### 3.15 数据导出

#### POST /admin/export/users — 导出C端用户

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — 导出平台流水

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

## 4. 限流策略

| 接口 | 限制 |
|------|------|
| 默认 | 60 次/分钟/IP |
| POST /api/auth/login | 10 次/分钟 |
| POST /api/auth/register | 5 次/分钟 |

超限返回 429，响应头包含：
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. 鉴权说明

### C端 (UserAuth)

1. 从 `Authorization: Bearer <token>` 提取 Token
2. JWT 验签（HS256），解析 `sub`（用户ID）
3. 查询 `erik_user` 表验证用户存在且 status=1
4. 注入 `$request->userId`

### 管理后台 (AdminAuth + AdminPermission)

1. AdminAuth: JWT 验签，解析 `sub`（管理员ID），注入 `$request->adminId`
2. AdminPermission: 根据用户角色查找权限，匹配 `method.path` 格式的权限标识
3. `slug=*` 的超级管理员跳过权限检查

## 6. 错误码速查

| code | 含义 | 常见场景 |
|------|------|---------|
| 0 | 成功 | - |
| 400 | 参数错误 | 请求格式不正确、金额不足 |
| 401 | 未认证 | Token 缺失/过期/无效、账号禁用 |
| 403 | 无权限 | 用户无对应角色权限、游戏不可用 |
| 404 | 不存在 | 资源未找到 |
| 422 | 验证失败 | 表单参数不符合规则、订单状态不允许操作 |
| 429 | 限流 | 请求过于频繁 |
| 500 | 服务端错误 | 未预期异常 |


## 7. 新增 API (v2.0 生态扩展)

### 7.1 Provider API — 游戏方回调接口

**认证方式**: HMAC-SHA256 签名 (X-Game-Id + X-Timestamp + X-Signature)
**时间窗口**: 5分钟

#### POST /api/provider/balance — 查询用户余额

```
请求头:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

请求: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

响应: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — 通知下注

```
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

响应: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — 通知结算

```
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

响应: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — 通知退款

```
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

响应: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 工单 API

#### GET /api/ticket/list — 工单列表

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
  "list": [
    {
      "id": "aB3xK...",
      "type": "deposit",
      "subject": "充值未到账",
      "status": "open",
      "priority": 0,
      "reply_count": 1,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3, "page": 1, "last_page": 1
}
```

type: deposit / withdraw / game / account / other
status: open / waiting / replied / closed

#### POST /api/ticket/create — 创建工单

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — 工单详情

```
需认证: 是
响应: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — 回复工单

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 邮箱验证 API

#### POST /api/verify/send-email — 发送邮箱验证码

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — 确认邮箱

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 VIP API

#### GET /api/user/vip-status — VIP状态

```
需认证: 是
响应: {
  "level": 2,
  "level_name": "Gold",
  "exp": 300,
  "total_exp": 2800,
  "next_level": { "level": 3, "name": "Platinum", "required_exp": 12500 },
  "benefits": {
    "exchange_discount": "0.05",
    "withdraw_fee_discount": "0.30",
    "rate_bonus": "0.003"
  }
}
```

### 7.5 成就 API

#### GET /api/user/achievements — 成就列表

```
需认证: 是
响应: {
  "achievements": [
    {
      "key": "first_deposit",
      "name": "First Deposit",
      "description": "Make your first deposit",
      "icon": "",
      "points": 20,
      "progress": 1,
      "completed": true
    }
  ]
}
```

### 7.6 管理后台新增 API

#### GET /admin/ticket/list — 工单列表

```
需认证: 是
参数: ?page=1&limit=20&status=pending&type=deposit

响应: {
  "list": [
    {
      "id": "...", "user_name": "player1",
      "type": "deposit", "subject": "...",
      "status": "open", "reply_count": 0,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5, "page": 1, "limit": 20
}
```

#### POST /admin/ticket/{hashid}/reply — 回复工单

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — 关闭工单

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — 指定处理人

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — 留存分析

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — 转化漏斗

```
需认证: 是
响应: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU 趋势

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — 游戏币种经济指标

```
需认证: 是
响应: {
  "currencies": [
    {
      "game_name": "Shooter Master",
      "currency": "Gold",
      "total_minted": "500000.0000",
      "total_burned": "320000.0000",
      "circulation": "180000.0000",
      "inflation_rate": "2.3%"
    }
  ]
}
```

## 8. 限流策略（更新）

| 接口 | 限制 |
|------|------|
| 默认 | 60 次/分钟/IP |
| POST /api/auth/login | 10 次/分钟 |
| POST /api/auth/register | 5 次/分钟 |
| POST /api/auth/oauth | 10 次/分钟 |
| POST /api/payment/callback | 30 次/分钟 |
| POST /api/provider/* | 无限制 (HMAC签名认证) |

## 9. 鉴权说明（更新）

### Provider 认证 (ProviderAuth)

1. 从请求头提取 `X-Game-Id`、`X-Timestamp`、`X-Signature`
2. 查询 `erik_game` 表验证游戏存在且 status=1
3. 验证时间戳在5分钟窗口内 (防重放)
4. 计算 `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` 与签名比对
5. 注入 `$request->gameId` 和 `$request->game`


### 7.7 好友 API

#### GET /api/friend/list — 好友列表
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — 待处理申请
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — 发送好友申请
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — 接受申请
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — 拒绝申请
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — 删除好友
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — 搜索用户
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 聊天 API

#### GET /api/chat/conversations — 会话列表
```
需认证: 是
响应: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — 消息列表
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — 发送消息
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — 未读总数
```
需认证: 是
响应: { "count": 5 }
```

**WebSocket 连接**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — 订阅列表
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — 注册订阅
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — 删除订阅
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 高级分析 API

#### GET /admin/analytics/retention — 留存分析
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — 转化漏斗
```
需认证: 是
响应: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU 趋势
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — 游戏经济指标
```
需认证: 是
响应: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 赛事 API

#### GET /api/tournament/list — 赛事列表
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — 赛事详情
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — 报名参赛
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 优惠券条件 (新增)

优惠券 `conditions` JSON 支持:
- `min_deposit`: 字符串, 最低累计充值金额
- `first_user_only`: bool, 仅限从未充值的新用户
- `game_id`: int, 需玩过指定游戏

条件在 `available()` 列表过滤和 `claim()` 领取时双重校验。

### 7.13 多级推荐 (新增)

推荐返佣增加二级分润:
- L1: 直接推荐人获得 `referrer_bonus` (配置: referral.referrer_bonus)
- L2: 推荐人的推荐人获得 `commission = referrer_bonus * level2_rate` (配置: referral.level2_rate, 默认 5%)
- 记录 `erik_referral_commission` (level/commission_rate/commission_amount)

### 8. 限流策略（更新）

| 接口 | 限制 |
|------|------|
| POST /api/tournament/{id}/join | 10 次/分钟 |
