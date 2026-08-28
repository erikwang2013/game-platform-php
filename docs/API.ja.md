# インターフェースドキュメント
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

オンライン対話式ドキュメント（オンラインデバッグ対応）:
- C端業務: http://localhost:8788/apidoc/
- 管理バックエンド: http://localhost:8787/apidoc/
- パスワード: admin123

## 1. 規約

### 1.1 ベース URL

| 端 | アドレス |
|----|------|
| 管理バックエンド | `http://localhost:8787` |
| C端業務 | `http://localhost:8788` |

### 1.2 共通リクエストヘッダー

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (需要认证的接口)
```

### 1.3 統一レスポンス形式

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 意味 |
|------|------|
| 0 | 成功 |
| 400 | パラメータエラー |
| 401 | 未認証（Token欠落/期限切れ/無効） |
| 403 | 権限なし |
| 404 | リソースが存在しない |
| 422 | 検証失敗 |
| 429 | リクエストが多すぎる（レートリミット発動） |
| 500 | サーバーエラー |

### 1.4 ID エンコード

すべてのインターフェースのリクエストとレスポンスの ID は Hashids エンコードされた文字列であり、元の BIGINT 値ではない。

```
外部: aB3xK9mW2pQ7rT5v  (hashid 字符串)
内部: 1750123456789      (Snowflake BIGINT)
```

### 1.5 ページネーション形式

```
请求: ?page=1&per_page=20

响应: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C端インターフェース (service :8788)

### 2.1 認証

#### POST /api/auth/register — ユーザー登録

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

#### POST /api/auth/login — ユーザーログイン

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

エラー: 401 ユーザー名またはパスワードが正しくない / アカウントが無効化されている

#### POST /api/auth/refresh — Token リフレッシュ

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 ウォレット

#### GET /api/wallet/info — ウォレット情報

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — 流水記録

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

### 2.3 チャージ

#### POST /api/deposit/create — チャージ注文の作成

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
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency 選択値: USD / CNY / EUR

checkout_url: 決済ゲートウェイのリダイレクトリンク（注文作成時に設定済み）；expires_at: 決済リンクの有効期限（作成から1時間）

#### GET /api/deposit/orders — チャージ記録

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

status 選択値: pending / paid / confirmed / cancelled

### 2.4 交換

#### POST /api/exchange/quote — 見積

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

direction: in=ゲームコイン購入 / out=ゲームコイン売却

#### POST /api/exchange/buy — ゲームコイン購入

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

エラー: 422 プラットフォームコイン残高不足 / 404 ゲームが利用不可

#### POST /api/exchange/sell — ゲームコイン売却

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

エラー: 422 ゲームコイン残高不足

#### GET /api/exchange/records — 交換記録

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

### 2.5 出金

#### POST /api/withdraw/apply — 出金申請

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

method 選択値: paypal / bank / crypto

status:
- approved: 自動通過（金額 < auto_approve_threshold）
- pending: 審査待ち（金額 >= auto_approve_threshold）

エラー:
- 403 出金機能が一時停止中（グローバルスイッチオフ）
- 400 最低出金額を下回っている
- 400 日次出金限度額を超過
- 400 残高不足

#### GET /api/withdraw/orders — 出金記録

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

### 2.6 ゲーム

#### GET /api/game/list — ゲーム一覧

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

type 選択値: self / third_party

#### GET /api/game/{hashid} — ゲーム詳細

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

#### POST /api/game/launch — ゲーム起動

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

### 2.7 OAuth サードパーティログイン

7 つのプラットフォームをサポート: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — 認可 URL の取得

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth コールバック

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=新規登録ユーザー / false=既存アカウント連携

### 2.8 KYC 実名認証

#### GET /api/user/identity/status — 認証状態

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

#### POST /api/user/identity/apply — 認証の提出

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

### 2.9 決済

#### POST /api/payment/callback — 決済コールバック（公開）

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

provider 選択値: stripe / paypal / nowpayments / coinbase（nowpayments は IPN HMAC-SHA512 で検証、coinbase は webhook HMAC-SHA256 で検証）

#### GET /api/payment/methods — 利用可能な決済方法（公開）

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

ユーザーの国でフィルタリング（X-Language/Accept-Language → 国コード変換）：countries が空または * を含む場合は全世界に表示；その国の country_config の支払い方法優先順位でソート

### 2.10 ゲーム記録

#### GET /api/game/play-logs — ゲーム記録一覧

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

#### GET /api/game/play-log/{hashid} — ゲーム記録詳細

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 ランキング

#### GET /api/leaderboard/list — ランキング一覧

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — ランキング詳細

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

### 2.13 クーポン

#### GET /api/coupon/available — 取得可能なクーポン

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — クーポン取得

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — マイクーポン

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 国別設定

#### GET /api/country/list — 国一覧

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — 国の詳細

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

#### GET /api/notification/list — 通知一覧

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

#### GET /api/notification/unread-count — 未読数

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — 既読化

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 紹介

#### GET /api/referral/my-code — マイ紹介コード

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — 紹介コードの使用

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA状態

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — 2FA設定

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — 2FA有効化

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — 2FA検証（公開）

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 検索

#### GET /api/search — グローバル検索

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — 検索サジェスト

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 言語

#### GET /api/language/list — 利用可能な言語一覧

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

#### POST /api/language/switch — 言語切り替え

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

locale 選択値: en-US / zh-CN / ja-JP / ko-KR

### 2.8 ユーザー

#### GET /api/user/profile — 個人情報

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

#### PUT /api/user/profile — プロフィール編集

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

language 選択値: en-US / zh-CN / ja-JP / ko-KR

### 2.9 公告

#### GET /api/announcement/list — 公告一覧

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

#### GET /api/announcement/detail/{hashid} — 公告詳細

```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. 管理バックエンドインターフェース (admin :8787)

### 3.1 プラットフォームダッシュボード

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

### 3.2 ゲーム管理

#### GET /admin/game/list — ゲーム一覧

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

#### POST /admin/game/create — ゲーム作成

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

type 選択値: self / third_party

#### PUT /admin/game/{hashid} — ゲーム編集

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — ゲーム削除

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — 通貨管理

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

### 3.3 出金管理

#### GET /admin/withdraw/orders — 出金注文一覧

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

#### PUT /admin/withdraw/review — 出金審査

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=通過 / reject=拒否（拒否時は自動的にプラットフォームコインへ戻す）

エラー: 422 注文ステータスが審査待ちではない

#### PUT /admin/withdraw/switch — グローバル出金スイッチ

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — 出金限度額の設定

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

### 3.4 プラットフォームユーザー管理

#### GET /admin/platform/user/list — C端ユーザー一覧

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

#### GET /admin/platform/user/{hashid} — ユーザー詳細

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

#### PUT /admin/platform/user/{hashid} — ユーザー編集/凍結

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 決済管理

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

#### POST /admin/payment/method/toggle — 決済方法の有効/無効

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

#### POST /admin/announcement/create — 公告の公開

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

### 3.7 KYC 審査

#### GET /admin/identity/list — KYC一覧

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

#### PUT /admin/identity/review — KYC審査

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 ゲーム区サーバー管理

#### GET /admin/game/server/list — 区サーバー一覧

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — 区サーバー作成

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — 区サーバー編集

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — 区サーバー削除

```
需认证: 是
```

### 3.9 出金段階別限度額管理

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

#### PUT /admin/withdraw/limits/{hashid} — 限度額の更新

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 ゲームカテゴリ管理

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

#### PUT /admin/game/category/{hashid} — カテゴリ編集

#### DELETE /admin/game/category/{hashid} — カテゴリ削除

#### POST /admin/game/category/assign — ゲーム割り当て

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 ランキング管理

#### GET /admin/leaderboard/list — ランキング一覧

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — ランキング作成

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — ランキング編集

#### DELETE /admin/leaderboard/{hashid} — ランキング削除

#### POST /admin/leaderboard/{hashid}/refresh — キャッシュリフレッシュ

### 3.13 クーポン管理

#### GET /admin/coupon/list — クーポン一覧

#### POST /admin/coupon/create — クーポン作成

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — 編集（未取得時のみ）

#### DELETE /admin/coupon/{hashid} — 削除

#### GET /admin/coupon/{hashid}/stats — 取得統計

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 国別設定管理

#### GET /admin/country/config/list — 国別設定一覧

#### POST /admin/country/config/create — 国別設定の作成

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — 国別設定の編集

### 3.15 データエクスポート

#### POST /admin/export/users — C端ユーザーのエクスポート

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — プラットフォーム流水のエクスポート

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 データ分析（MySQL リアルタイム集計）

すべてのエンドポイントは認証が必要（AdminAuth + AdminPermission）、データは MySQL からリアルタイム集計され、ClickHouse には依存しない。

| 方法 | パス | 説明 |
|------|------|------|
| GET | /admin/analytics/overview | プラットフォーム総覧（今日/直近7日） |
| GET | /admin/analytics/game-ranking | ゲームランキング（?days=7） |
| GET | /admin/analytics/dau-trend | DAU トレンド（?days=30） |
| GET | /admin/analytics/hourly-trend | 時間別トレンド |
| GET | /admin/analytics/action-distribution | 行動分布 |
| GET | /admin/analytics/revenue | 収益分析 |
| GET | /admin/analytics/conversion | ゲームコンバージョン率 |
| GET | /admin/analytics/probability | 結合/条件確率 |
| GET | /admin/analytics/retention | リテンション分析 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | コンバージョンファネル |
| GET | /admin/analytics/arpu | ARPU/ARPPU トレンド |
| GET | /admin/analytics/economy | ゲーム通貨経済指標 |

### 3.17 チケット管理

すべてのエンドポイントは認証が必要（AdminAuth + AdminPermission）。

| 方法 | パス | 説明 |
|------|------|------|
| GET | /admin/ticket/list | チケット一覧（?page=&limit=&status=&type=） |
| GET | /admin/ticket/{hashid} | チケット詳細（返信含む） |
| POST | /admin/ticket/{hashid}/reply | チケット返信 |
| POST | /admin/ticket/{hashid}/close | チケットクローズ |
| POST | /admin/ticket/{hashid}/assign | 担当者の指定（admin_id） |

## 4. レートリミット戦略

| エンドポイント | 制限 |
|------|------|
| デフォルト | 60 回/分/IP |
| POST /api/auth/login | 10 回/分 |
| POST /api/auth/register | 5 回/分 |

超過時は 429 を返し、レスポンスヘッダーに含まれる:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. 認証の説明

### C端 (UserAuth)

1. `Authorization: Bearer <token>` から Token を抽出
2. JWT 署名検証（HS256）、`sub`（ユーザーID）を解析
3. `game_user` テーブルを照会してユーザーが存在し status=1 であることを検証
4. `$request->userId` に注入

### 管理バックエンド (AdminAuth + AdminPermission)

1. AdminAuth: JWT 署名検証、`sub`（管理者ID）を解析、`$request->adminId` に注入
2. AdminPermission: ユーザーのロールから権限を探し、`method.path` 形式の権限識別子と照合
3. `slug=*` のスーパー管理者は権限チェックをスキップ

## 6. エラーコード早見表

| code | 意味 | 一般的なシナリオ |
|------|------|---------|
| 0 | 成功 | - |
| 400 | パラメータエラー | リクエスト形式が不正、残高不足 |
| 401 | 未認証 | Token 欠落/期限切れ/無効、アカウント無効化 |
| 403 | 権限なし | ユーザーに対応するロール権限がない、ゲームが利用不可 |
| 404 | 存在しない | リソースが見つからない |
| 422 | 検証失敗 | フォームパラメータがルールに違反、注文状態が操作を許可しない |
| 429 | レートリミット | リクエストが多すぎる |
| 500 | サーバーエラー | 予期しない例外 |


## 7. 追加 API (v2.0 エコシステム拡張)

### 7.1 Provider API — ゲーム側コールバックインターフェース

**認証方式**: HMAC-SHA256 署名 (X-Game-Id + X-Timestamp + X-Signature)
**時間ウィンドウ**: 5分

#### POST /api/provider/balance — ユーザー残高の照会

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

#### POST /api/provider/bet — 下注通知

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

#### POST /api/provider/settle — 決算通知

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

#### POST /api/provider/refund — 返金通知

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

### 7.2 チケット API

#### GET /api/ticket/list — チケット一覧

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

#### POST /api/ticket/create — チケット作成

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — チケット詳細

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

#### POST /api/ticket/{hashid}/reply — チケット返信

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 メール検証 API

#### POST /api/verify/send-email — メール認証コード送信

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — メール確認

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 VIP API

#### GET /api/user/vip-status — VIP状態

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

### 7.5 アチーブメント API

#### GET /api/user/achievements — アチーブメント一覧

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

### 7.6 管理バックエンド追加 API

#### GET /admin/ticket/list — チケット一覧

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

#### POST /admin/ticket/{hashid}/reply — チケット返信

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — チケットクローズ

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — 担当者指定

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — リテンション分析

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — コンバージョンファネル

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

#### GET /admin/analytics/arpu — ARPU/ARPPU トレンド

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — ゲーム通貨経済指標

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

## 8. レートリミット戦略（更新）

| エンドポイント | 制限 |
|------|------|
| デフォルト | 60 回/分/IP |
| POST /api/auth/login | 10 回/分 |
| POST /api/auth/register | 5 回/分 |
| POST /api/auth/oauth | 10 回/分 |
| POST /api/payment/callback | 30 回/分 |
| POST /api/provider/* | 制限なし (HMAC署名認証) |

## 9. 認証の説明（更新）

### Provider 認証 (ProviderAuth)

1. リクエストヘッダーから `X-Game-Id`、`X-Timestamp`、`X-Signature` を抽出
2. `game_game` テーブルを照会してゲームが存在し status=1 であることを検証
3. タイムスタンプが5分ウィンドウ内であることを検証 (リプレイ防止)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` を計算し署名と照合
5. `$request->gameId` と `$request->game` に注入


### 7.7 フレンド API

#### GET /api/friend/list — フレンド一覧
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — 未処理の申請
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — フレンド申請の送信
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — 申請の承認
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — 申請の拒否
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — フレンド削除
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — ユーザー検索
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 チャット API

#### GET /api/chat/conversations — 会話一覧
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

#### GET /api/chat/messages/{peerHashid} — メッセージ一覧
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — メッセージ送信
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — 未読総数
```
需认证: 是
响应: { "count": 5 }
```

**WebSocket 接続**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — 購読一覧
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — 購読登録
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — 購読削除
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 高度な分析 API

#### GET /admin/analytics/retention — リテンション分析
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — コンバージョンファネル
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

#### GET /admin/analytics/arpu — ARPU/ARPPU トレンド
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — ゲーム経済指標
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


### 7.11 トーナメント API

#### GET /api/tournament/list — トーナメント一覧
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — トーナメント詳細
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — 大会参加登録
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 クーポン条件（追加）

クーポンの `conditions` JSON がサポート:
- `min_deposit`: 文字列、最低累計チャージ金額
- `first_user_only`: bool、チャージ実績のない新規ユーザーのみ
- `game_id`: int、指定ゲームのプレイ実績が必要

条件は `available()` 一覧のフィルタリングと `claim()` 取得時の二重で検証される。

### 7.13 多段階紹介（追加）

紹介報酬に二級分潤を追加:
- L1: 直接紹介者が `referrer_bonus` を獲得 (設定: referral.referrer_bonus)
- L2: 紹介者の紹介者が `commission = referrer_bonus * level2_rate` を獲得 (設定: referral.level2_rate、デフォルト 5%)
- `game_referral_commission` に記録 (level/commission_rate/commission_amount)

### 8. レートリミット戦略（更新）

| エンドポイント | 制限 |
|------|------|
| POST /api/tournament/{id}/join | 10 回/分 |
