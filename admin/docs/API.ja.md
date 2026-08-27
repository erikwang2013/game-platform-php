# API リファレンスドキュメント
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · **日本語**


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 概要

オープン管理画面 (open-admin) は webman v2 ベースで構築され、RESTful JSON API を提供します。すべての管理画面APIは JWT 認証と RBAC 権限チェックが必要で、公開APIは API バージョンヘッダーによってバージョン化されたコントローラーにルーティングされます。

- **ベース URL**: `http://localhost:8787`
- **API バージョン**: リクエストヘッダー `API-Version: v1` で制御（欠落時はデフォルト v1）

> **エンドポイント総数**: 認証(5) | ダッシュボード(1) | ユーザー(7) | ロール(4) | 権限(4) | 設定(4) | ログ(1) | 個人センター(3) | インポート・エクスポート(3) | アップロード(1) | 運用(4: health/metrics/docs/security.txt) | 合計 37 エンドポイント
- **認証**: `Authorization: Bearer <token>`（JWT）
- **レスポンス形式**: `{ "code": 0, "message": "success", "data": {...} }`
- **ドキュメントエンドポイント**: `GET /api/docs` が OpenAPI 3.0 JSON 仕様を返す

### リクエスト要件

- `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` メソッドのみ許可。その他の HTTP メソッド（TRACE、CONNECT、PATCH など）を使用すると 405 が返される
- すべての `POST` / `PUT` リクエストは `Content-Type: application/json` を設定する必要がある（ファイルアップロードを除く）。そうでない場合は 415 が返される
- リクエストボディは 10MB を超えてはならない。超えた場合は 413 が返される
- セキュリティフィルターはすべてのリクエスト入力に対して XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションをスキャンし、ヒットした場合は 403 を返す
- ログイン連続5回失敗でアカウントロック（15分）が発動し、ロック中のログインリクエストは 429 を返す
- 同一ユーザーが同時に保持できる有効 Token は最大3つ。超過時は最も古い Token が自動的にブラックリスト入り

## 2. エラーコード

| code | 意味 | 発生シーン |
|------|------|---------|
| 0 | 成功 | |
| 400 | リクエストパラメータエラー | リクエスト形式が正しくない |
| 401 | 未認証 | Token 欠落 / 期限切れ / ブラックリスト入り |
| 403 | 権限なし / セキュリティ遮断 | RBAC 権限不足 / SecurityFilter ヒット |
| 404 | リソースが存在しない | 照会/更新/削除の対象が存在しない |
| 405 | リクエストメソッドが許可されていない | GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可、非標準メソッドは直接拒否 |
| 413 | リクエストボディが大きすぎる | Content-Length が 10MB 超過 |
| 415 | サポートされていないメディアタイプ | POST/PUT リクエストの Content-Type が JSON 以外かつファイルアップロードでない |
| 422 | パラメータ検証失敗 | 必須フィールド欠落、形式不一致、ビジネス検証不通過 |
| 429 | リクエストが多すぎる | RateLimit 発動 / アカウントロック（ログイン連続5回失敗で15分間ロック） |
| 500 | サーバー内部エラー | |

## 3. 公開エンドポイント

すべての公開エンドポイントは `/api` グループ配下にマウントされ、`ApiVersion` 中間ウェアによって `API-Version` ヘッダーに応じて対応するバージョン化コントローラー（例：`app\api\v1\controller\AuthController`）に振り分けられます。

### 3.1 ヘルスチェック

```
GET /health
```

- **認証**: 不要
- **レート制限**: なし

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`、`redis`、`elasticsearch` の値: `"ok"` | `"unavailable"`。`elasticsearch` は ES に到達できない場合 `"unavailable"` を返し、クラスタの健全性ステータスが green/yellow 以外の場合は実際の status 値（例：`"red"`）を返します。

### 3.2 API ドキュメント

```
GET /api/docs
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)
- **レスポンス**: OpenAPI 3.0.3 JSON 仕様、全エンドポイント定義、パラメータ、Schema を含む

### 3.3 クリック型CAPTCHAの生成

```
POST /api/captcha/generate
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "difficulty": "medium"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| difficulty | string | いいえ | `easy` / `medium` / `hard`、デフォルト `medium` |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| key | string | キャプチャ識別子、検証時に返送 |
| image | string | base64 エンコードされた PNG 画像 |
| extra.targets[].order | int | クリック順序 |
| extra.targets[].text | string | クリック対象のヒント文字 |

### 3.4 クリック型CAPTCHAの検証

```
POST /api/captcha/verify
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| key | string | はい | キャプチャ key、generate が返した値 |
| clicks | array{object} | はい | クリック座標配列、各要素は `x`（int）と `y`（int）を含む |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

検証失敗時は `code` が 422、`message` が `"验证失败，请重试"`、`data.valid` が `false` になります。

### 3.5 ログイン

```
POST /api/auth/login
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: 10 回/分（IP + パス単位）

**リクエストボディ**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名 |
| password | string | はい | min:6, max:32 | パスワード |
| captcha_key | string | はい | | キャプチャ key |
| clicks | array{object} | はい | min:2 | クリック座標配列 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| access_token | string | JWT アクセストークン |
| refresh_token | string | JWT リフレッシュトークン |
| expires_in | int | アクセストークン有効期間（秒）、デフォルト 7200 |
| user.id | string | hashid 暗号化されたユーザー ID |
| user.username | string | ユーザー名 |
| user.real_name | string | 実名 |

**考えられるエラー**:
- 422: パラメータ検証失敗（必須フィールド欠落、形式不一致）
- 422: キャプチャエラー、やり直してください
- 401: ユーザー名またはパスワードが誤り
- 403: アカウントが無効化されている
- 429: アカウントがロックされています。15分後にお試しください（ログイン連続5回失敗で発動）

### 3.6 登録

```
POST /api/auth/register
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: 5 回/分（IP + パス単位）

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名（一意） |
| password | string | はい | min:6, max:32 | パスワード（bcrypt ハッシュで保存） |
| real_name | string | はい | max:50 | 実名 |
| captcha_key | string | はい | | キャプチャ key |
| clicks | array{object} | はい | min:2 | クリック座標配列 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

登録成功後、JWT トークンが直接返されます。ユーザー状態はデフォルトで有効（status=1）です。

### 3.7 トークン更新

```
POST /api/auth/refresh
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| refresh_token | string | はい | ログイン/登録時に取得した refresh_token |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

更新成功時は新しい access_token と refresh_token が同時に返され、古いトークンは自動的に無効になります。更新時にはユーザーの最終ログイン時刻と IP が更新されます。

**考えられるエラー**:
- 422: リフレッシュトークンがありません
- 401: リフレッシュトークンが無効または期限切れ

### 3.8 Prometheus 監視メトリクス

```
GET /metrics
```

- **認証**: 不要
- **レート制限**: なし
- **レスポンス形式**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus が収集するための公開 Prometheus 監視メトリクスエンドポイント。

**レスポンス例**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| メトリクス名 | 型 | 説明 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 累計 HTTP リクエスト総数 |
| `openadmin_active_users` | gauge | 現在のアクティブユーザー数（24時間以内にログイン） |
| `openadmin_db_connection_status` | gauge | データベース接続状態、1=正常, 0=異常 |
| `openadmin_redis_connection_status` | gauge | Redis 接続状態、1=正常, 0=異常 |
| `openadmin_memory_usage_bytes` | gauge | PHP プロセスの現在のメモリ使用量（bytes） |

## 4. ダッシュボード

すべての管理画面APIは `/admin` グループ配下にマウントされ、`AdminAuth`（JWT 認証）、`AdminPermission`（RBAC 権限チェック）、`OperationLog`（操作記録）の3つの中間ウェアを通過します。

### 4.1 ダッシュボードデータ

```
GET /admin/dashboard
```

- **認証**: JWT + RBAC
- **キャッシュ**: Redis 5 分

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats フィールド | 型 | 説明 |
|------|------|------|
| label | string | 指標名 |
| value | string | 指標値（文字列型） |
| icon | string | Material アイコン名 |
| color | string | カードの色値 |
| trend | float? | 日次前日比成長率（パーセント）、"ユーザー総数"のみこのフィールドを持つ |

| trends フィールド | 型 | 説明 |
|------|------|------|
| dates | array{string} | 直近30日間の日付シーケンス |
| series | array{object} | トレンドラインデータ、各項目は name（名前）、data（数値配列）、color（色）を含む |

## 5. ユーザー管理

すべてのユーザー管理APIが返す `id` は hashid 暗号化文字列です。パスワードフィールドはレスポンスから除外されています。携帯番号とメールアドレスは一覧APIではマスキング表示され、詳細APIでは平文で返されます（データベース暗号化フィールドは Encryptable trait が自動復号）。

### 5.1 ユーザー一覧

```
GET /admin/user
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1ページあたりの件数 |
| keyword | string | いいえ | | 検索キーワード、ユーザー名と実名を照合 |
| status | int | いいえ | | 状態フィルター、0=無効、1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたユーザー ID |
| username | string | ユーザー名 |
| real_name | string | 実名 |
| phone | string | マスキング済み携帯番号（`138****5678` 形式） |
| email | string | マスキング済みメールアドレス（`a***@example.com` 形式） |
| status | int | 1=有効, 0=無効 |
| last_login_at | string | 最終ログイン時刻 (datetime) |
| created_at | string | 作成時刻 (datetime) |

### 5.2 ユーザー作成

```
POST /admin/user
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | はい | min:3, max:50 | ユーザー名（一意） |
| password | string | はい | min:6, max:32 | パスワード（bcrypt 保存） |
| real_name | string | はい | max:50 | 実名 |
| phone | string | いいえ | | 携帯番号（Encryptable 暗号化保存） |
| email | string | いいえ | | メールアドレス（Encryptable 暗号化保存） |
| status | int | いいえ | in:0,1 | 状態、デフォルト 1（有効） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**考えられるエラー**:
- 422: ユーザー名が既に存在します
- 422: パラメータ検証失敗（必須フィールド欠落）

### 5.3 ユーザー詳細

```
GET /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

詳細APIでは `phone` と `email` は平文で返されます（データベース上は暗号化保存、Encryptable cast が自動復号）、マスキングされません。`password` と `id_card` は常にレスポンスに含まれません。

**考えられるエラー**:
- 404: ユーザーが存在しない

### 5.4 ユーザー更新

```
PUT /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | いいえ | 実名、未指定の場合は元の値を保持 |
| password | string | いいえ | 新パスワード、空文字列または未指定の場合は変更しない |
| phone | string | いいえ | 携帯番号 |
| email | string | いいえ | メールアドレス |
| status | int | いいえ | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**考えられるエラー**:
- 404: ユーザーが存在しない

### 5.5 ユーザー削除

```
DELETE /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID
- **機密操作**: パスワード再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| password | string | はい | 現在ログイン中のユーザーのパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ソフト削除（Eloquent SoftDeletes）を実行し、データは deleted_at をマークされ物理削除されません。

**考えられるエラー**:
- 404: ユーザーが存在しない
- 422: 機密操作にはパスワード入力による確認が必要です（password が空）
- 422: パスワード検証失敗（パスワード不一致）

### 5.6 ユーザー一括削除

```
POST /admin/user/batch/destroy
```

- **認証**: JWT + RBAC
- **機密操作**: パスワード再確認が必要

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | はい | hashid 暗号化されたユーザー ID 配列 |
| password | string | はい | 現在ログイン中のユーザーのパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

ソフト削除を実行し、`data.count` が実際の削除数です。

**考えられるエラー**:
- 422: 削除するユーザーを選択してください（ids が空）
- 422: 無効な ID（hashid デコード失敗）
- 422: パスワード検証失敗

### 5.7 ユーザー一括有効化/無効化

```
POST /admin/user/batch/status
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | はい | hashid 暗号化されたユーザー ID 配列 |
| status | int | はい | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message は status 値に応じて `"批量启用成功"` または `"批量禁用成功"` に動的に変化します。

**考えられるエラー**:
- 422: ユーザーを選択してください（ids が空）
- 422: 状態値が無効（status が 0 または 1 でない）

## 6. ロール管理

### 6.1 ロール一覧

```
GET /admin/role
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1ページあたりの件数 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたロール ID |
| name | string | ロール名 |
| slug | string | ロール識別子（一意、権限判定に使用） |
| description | string | ロール説明 |
| status | int | 1=有効, 0=無効 |
| users_count | int | このロールを持つユーザー数 |

### 6.2 ロール作成

```
POST /admin/role
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| name | string | はい | max:50 | ロール名 |
| slug | string | はい | max:50 | ロール識別子 |
| description | string | いいえ | | ロール説明、デフォルト空文字列 |
| status | int | いいえ | | 状態、デフォルト 1 |
| permission_ids | array{int} | いいえ | | 権限 ID 配列（元の INT ID、hashid ではない） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 ロール更新

```
PUT /admin/role/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | いいえ | ロール名 |
| description | string | いいえ | 説明 |
| status | int | いいえ | 0=無効, 1=有効 |
| permission_ids | array{int} | いいえ | 権限 ID 配列、渡すとロール権限を同期（上書き） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 ロール削除

```
DELETE /admin/role/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワード再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時、ロールとすべての権限・ユーザーの関連付けを自動的に解除し、ロールレコードを物理削除します。

## 7. 権限管理

権限はツリー構造（parent_id 自己参照）を採用し、3種類に分類されます。一覧APIは完全な権限ツリーを返します。

### 7.1 権限ツリー

```
GET /admin/permission
```

- **認証**: JWT + RBAC

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化 |
| parent_id | string | 親権限の hashid、"0" はルートノード |
| name | string | 権限名 |
| slug | string | 権限識別子（ルート/ボタン識別子） |
| type | int | 1=メニュー, 2=ボタン, 3=API |
| icon | string | メニューアイコン（Material アイコン名） |
| path | string | フロントエンドルートパス |
| sort | int | ソート値（昇順） |
| children | array? | 子権限リスト（再帰）、子ノードがない場合はこのフィールドを含まない |

### 7.2 権限作成

```
POST /admin/permission
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| parent_id | int | いいえ | | 親権限 ID（元の INT 型）、デフォルト 0 |
| name | string | はい | max:50 | 権限名 |
| slug | string | はい | max:100 | 権限識別子 |
| type | int | はい | in:1,2,3 | 1=メニュー, 2=ボタン, 3=API |
| icon | string | いいえ | | メニューアイコン、デフォルト空 |
| path | string | いいえ | | フロントエンドルートパス、デフォルト空 |
| sort | int | いいえ | | ソート値、デフォルト 0 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 権限更新

```
PUT /admin/permission/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | いいえ | 権限名 |
| icon | string | いいえ | アイコン |
| path | string | いいえ | ルートパス |
| sort | int | いいえ | ソート値 |

### 7.4 権限削除

```
DELETE /admin/permission/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワード再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時、すべての子権限（`parent_id` = 現在の権限 ID のレコード）をカスケード削除し、すべてのロールとの関連付けも解除します。

## 8. システム設定

システム設定は `group` + `key` の組み合わせで一意になります。

### 8.1 設定一覧

```
GET /admin/config
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1ページあたりの件数 |
| group | string | いいえ | | 設定グループでフィルター |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| group | string | 設定グループ（例：`system`、`email`、`storage`） |
| key | string | 設定キー |
| value | string | 設定値 |
| type | string | 値の型ヒント（`string`、`integer`、`boolean`、`json` など） |
| description | string | 設定の説明 |

### 8.2 設定作成

```
POST /admin/config
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| group | string | はい | max:100 | 設定グループ |
| key | string | はい | max:100 | 設定キー（同一グループ内で一意） |
| value | string | はい | | 設定値 |
| type | string | いいえ | | 値の型、デフォルト `string` |
| description | string | いいえ | | 設定の説明、デフォルト空 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**考えられるエラー**:
- 422: 設定項目が既に存在します（同じ group + key）

### 8.3 設定更新

```
PUT /admin/config/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| value | string | いいえ | 設定値の更新 |
| type | string | いいえ | 値の型の更新 |
| description | string | いいえ | 説明文の更新 |

### 8.4 設定削除

```
DELETE /admin/config/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワード再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

設定レコードを物理削除します。

## 9. 操作ログ

操作ログは読み取り専用APIで、`OperationLog` 中間ウェアが POST/PUT/DELETE リクエストのたびに自動的に書き込みます。保存フィールドは `user_id`、`action`、`method`、`path`、`ip`、`source`、`input` です。

### 9.1 操作ログ一覧

```
GET /admin/log
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | いいえ | 1 | ページ番号 |
| limit | int | いいえ | 15 | 1ページあたりの件数 |
| user_id | int | いいえ | | ユーザー ID で完全一致フィルター（元の INT 型） |
| action | string | いいえ | | 操作アクションで完全一致フィルター |
| path | string | いいえ | | リクエストパスで部分一致フィルター |
| start_date | string | いいえ | | 開始日 (Y-m-d 形式) |
| end_date | string | いいえ | | 終了日 (Y-m-d 形式) |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| user_name | string | 操作ユーザー名（user 関連から取得、未ログイン操作は"システム"表示） |
| action | string | 操作アクションの説明 |
| method | string | HTTP メソッド（POST/PUT/DELETE） |
| path | string | リクエストパス |
| ip | string | クライアント IP |
| source | string | リクエスト送信元 |
| input | string | リクエストパラメータの JSON 文字列（ファイルは含まない） |
| created_at | string | 操作時刻 (datetime) |

## 10. 個人センター

個人センターAPIは JWT 認証のみ必要です（RBAC 権限チェックは不要——`AdminPermission` 中間ウェアがホワイトリストに追加します）。

### 10.1 個人情報の更新

```
PUT /admin/profile
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | いいえ | 実名 |
| phone | string | いいえ | 携帯番号（Encryptable 暗号化保存） |
| email | string | いいえ | メールアドレス（Encryptable 暗号化保存） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

レスポンスの `phone` と `email` は平文で返され、`password` と `id_card` は除外されています。

### 10.2 パスワード変更

```
PUT /admin/profile/password
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| old_password | string | はい | | 現在のパスワード |
| new_password | string | はい | min:6, max:32 | 新パスワード |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**考えられるエラー**:
- 422: 旧パスワードと新パスワードを入力してください
- 422: 旧パスワードが誤り
- 422: 新パスワードは6〜32文字

### 10.3 ログアウト

```
POST /admin/profile/logout
```

- **認証**: JWT

**リクエストボディ**: なし（requestBody なし、Authorization ヘッダーから token を読み取る）

**レスポンス例**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

ログアウトのロジック: JWT をデコードして残りの有効期間 (exp - now) を取得し、そのトークンの md5 ハッシュを Redis ブラックリスト `jwt_blacklist:{md5}` に書き込み、TTL = 残りの有効期間。ブラックリスト内のトークンは `AdminAuth` 中間ウェアで遮断され、401 を返します。

token がない場合は 401 を返します。token が期限切れ/無効な場合（デコード時に例外発生）もログアウト成功とみなされます。

## 11. インポート・エクスポート

### 11.1 Excel エクスポート

```
POST /admin/export/excel
```

- **認証**: JWT + RBAC
- **レスポンス型**: ファイルダウンロード（`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`）

**リクエストボディ**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| table | string | いいえ | `admin_user` | エクスポートするテーブル名。対応: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | いいえ | | エクスポートする列のフィールド名配列、空の場合は該当テーブルの全列をエクスポート |
| conditions | object | いいえ | `{}` | フィルター条件、key-value ペア、値が空でない場合 WHERE に使用 |
| title | string | いいえ | `数据导出` | Excel タイトル（Sheet 名として表示） |

**対応テーブルと列**:

| table | 利用可能な列 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

機密フィールド `phone`、`email`、`id_card` はエクスポート時に自動マスキングされます。データ上限は 10000 行。Excel の先頭行は固定表示、自動フィルター付き。

### 11.2 PDF エクスポート

```
POST /admin/export/pdf
```

- **認証**: JWT + RBAC
- **レスポンス型**: ファイルダウンロード（`application/pdf`、A4 横向き）

**リクエストボディ**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

またはテーブルモード:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| type | string | いいえ | `table` | エクスポートタイプ：`table` / `dashboard` |
| title | string | いいえ | `数据导出` | PDF タイトル |
| data | object | いいえ | `{}` | エクスポートデータ |

`type=dashboard` のとき `data` に `stats` 配列（カード形式でレンダリング）が必要です。`type=table` のとき `data` に `columns` と `rows` 配列が必要です。

PDF テンプレートには著作権情報とエクスポート時刻が含まれます。

### 11.3 ユーザーインポート (Excel)

```
POST /admin/import/users
```

- **認証**: JWT + RBAC
- **リクエスト型**: `multipart/form-data`（ファイルアップロード）

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | はい | `.xlsx` または `.xls` 形式 |

**Excel 列の要件**:

| 列名 | 必須 | 説明 |
|------|------|------|
| username | はい | ユーザー名（一意） |
| password | はい | パスワード（bcrypt ハッシュで保存） |
| real_name | はい | 実名 |
| phone | いいえ | 携帯番号 |
| email | いいえ | メールアドレス |
| status | いいえ | 状態、デフォルト 1 |

1行目は列タイトル（大文字小文字を区別しない）、2行目以降がデータです。

**レスポンス例**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| total | int | 総行数（タイトル行を含まない） |
| success | int | インポート成功数 |
| failed | int | 失敗数 |
| errors | array | 失敗詳細、各項目は row（Excel 行番号）と reason（失敗理由）を含む |

## 12. ファイルアップロード

```
POST /admin/upload
```

- **認証**: JWT + RBAC
- **リクエスト型**: `multipart/form-data`

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | はい | アップロードファイル |

**許可されるファイルタイプ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**最大ファイルサイズ**: 10MB

**レスポンス例**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ファイルは日付ごとのディレクトリ `public/upload/{Y-m-d}/` に保存され、ファイル名は `md5(uniqid) + 元の拡張子` です。`url` はサイトルートからの相対パスです。

**考えられるエラー**:
- 422: ファイルを選択してください（未アップロード）
- 422: サポートされていないファイルタイプ
- 422: ファイルサイズは 10MB を超えられません
- 500: ファイルアップロード失敗（ファイルが無効）

## 13. レスポンスヘッダー

すべてのAPI（グローバル中間ウェア層で注入）には以下のレスポンスヘッダーが含まれます：

| ヘッダー | 説明 |
|----|------|
| `X-RateLimit-Limit` | レート制限上限（回数） |
| `X-RateLimit-Remaining` | 残りリクエスト回数 |
| `X-RateLimit-Reset` | レート制限ウィンドウのリセットタイムスタンプ |
| `Retry-After` | レート制限発動時のみ返され、待機推奨秒数 |
| `X-Content-Type-Options` | `nosniff`（webman デフォルト、MIME スニッフィング禁止） |
| `X-Frame-Options` | `DENY`（webman の CORS 中間ウェア/基本設定が提供） |

レート制限の詳細:
- デフォルトのグローバル制限: 60 回/分 / IP+パス
- ログインエンドポイント `/api/auth/login`: 10 回/分
- 登録エンドポイント `/api/auth/register`: 5 回/分
- Redis 原子化スライディングウィンドウアルゴリズム（Lua ZSET）を使用し、TOCTOU 競合を回避
- Redis 利用不可時は fail-closed：503（`Retry-After: 5`）を返し、リクエストを通さない

## 14. データ分析 (Analytics)

全エンドポイントで認証（`AdminAuth` + `AdminPermission`）が必要、MySQL リアルタイム集計、合計 12 個：

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/analytics/overview | プラットフォーム概要（今日/直近7日） |
| GET | /admin/analytics/game-ranking | ゲームランキング（?days=7） |
| GET | /admin/analytics/dau-trend | DAU トレンド（?days=30） |
| GET | /admin/analytics/hourly-trend | 時間別トレンド |
| GET | /admin/analytics/action-distribution | 行動分布 |
| GET | /admin/analytics/revenue | 売上分析 |
| GET | /admin/analytics/conversion | ゲームコンバージョン率 |
| GET | /admin/analytics/probability | 結合/条件確率 |
| GET | /admin/analytics/retention | リテンション分析 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | コンバージョンファネル |
| GET | /admin/analytics/arpu | ARPU/ARPPU トレンド |
| GET | /admin/analytics/economy | ゲーム通貨の経済指標 |

## 15. チケット管理 (Ticket)

全エンドポイントで認証（`AdminAuth` + `AdminPermission`）が必要、合計 5 個：

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/ticket/list | チケット一覧（?page=&limit=&status=&type=） |
| GET | /admin/ticket/{hashid} | チケット詳細（返信含む） |
| POST | /admin/ticket/{hashid}/reply | チケットに返信 |
| POST | /admin/ticket/{hashid}/close | チケットをクローズ |
| POST | /admin/ticket/{hashid}/assign | 処理担当者を指定（admin_id） |

## 16. 認証フロー

完全な認証シーケンス：

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 未登录（adminId 为空）→ 401
   b. 对资源路由解析权限标识
   c. 查询用户角色 → 角色权限，进行匹配
   d. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT 構造

- **access_token**: `{ sub: <user_id>, username: "<name>" }`、デフォルト TTL 7200 秒（JWT 設定 `default_expire` で制御）
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`、デフォルト TTL 1209600 秒（JWT 設定 `refresh_expire` で制御、つまり 14 日）

### セキュリティ管理

- パスワードは `PASSWORD_BCRYPT` ハッシュで保存
- 機密フィールド（phone, email, id_card）は `erikwang2013/encryptable` でデータベース層にて透過的に暗号化・復号
- API層の ID は `erikwang2013/hashids` で暗号化転送し、元の snowflake ID シーケンスの露出を回避
- SecurityFilter がグローバルに XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションをスキャン、同一 IP 5回/60秒で一時ブラックリスト 15 分
- 機密操作（ユーザー・ロール・権限・設定の削除）には現在ログイン中のユーザーのパスワードによる再確認が必要
- 同時セッション制限：同一ユーザーの有効 Token は最大3つ、4つ目のデバイスでログインすると最も古い Token が強制的にブラックリスト入り
- アカウントロック：ログイン連続5回失敗で15分のアカウントロックが発動、ロック中は 429 を返す

## 15. デプロイ運用

### Docker Compose

プロジェクトルートに `docker-compose.yml` があり、5つのサービス（Nginx、webman app、MySQL、Redis、Elasticsearch）を構成。PHP は `Dockerfile` でビルド（`php:8.3-cli` ベース、OPcache 有効）。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` が GitHub Actions 継続的インテグレーションパイプラインを定義：
- `php -l` 構文チェック
- PHPUnit ユニットテスト
- `flutter analyze` 静的解析

### データベースバックアップ

`database/backup/` ディレクトリがバックアップ・リストアスクリプトを提供：
- `backup.sh` — mysqldump + gzip 圧縮バックアップ、30日前の古いバックアップファイルを自動削除
- `restore.sh` — 対話式リストア、既存のバックアップを一覧表示して選択

### Nginx セキュリティ設定

本番環境のデプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティ強化を設定してください。

## 16. データ分析 (Analytics)

データ分析APIは `AnalyticsController` が提供し、すべて MySQL リアルタイム集計（`game_game_play_log` ゲーム行動ログ / `game_deposit_order` 入金注文）に基づきます。データベース障害時は 500 ではなく空データを返します。特に記載がない限り JWT + RBAC 認証が必要で、レスポンスのラッパー形式は統一して `{ "code": 0, "message": "success", "data": ... }` です。

### 16.1 プラットフォーム概要

```
GET /admin/analytics/overview
```

**レスポンス**: `today` / `week` にそれぞれ `dau`（アクティブユーザー数）、`revenue`（確認済み入金総額、文字列）、`new_users`（新規ユーザー数）を含む。

### 16.2 ゲームランキング

```
GET /admin/analytics/game-ranking?days=7
```

**レスポンス**: ゲーム行動回数の降順で上位10件、各項目に `game_id`（hashid）、`name`、`plays`、`players` を含む。

### 16.3 DAU トレンド

```
GET /admin/analytics/dau-trend?days=30
```

**レスポンス**: `{ "日期": 活跃数, ... }`、欠落した日付は 0 で補完。

### 16.4 時間別トレンド

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**レスポンス**: `{ "0": 次数, ... "23": 次数 }` の24時間スロット。`game_id` が空の場合は全ゲームを集計。

### 16.5 行動分布

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**レスポンス**: `{ "start": n, "end": n, "earn": n, "spend": n }` の4種類の行動カウント。`hours` 上限は 168。

### 16.6 売上概要

```
GET /admin/analytics/revenue?days=7
```

**レスポンス**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`、`status=confirmed` の注文のみ集計。

### 16.7 ゲームコンバージョン率

```
GET /admin/analytics/conversion?days=30
```

**レスポンス**: 各ゲームに `game_id`（hashid）、`game_name`、`players`（重複除去済みプレイヤー数）、`depositors`（重複除去済み入金人数）、`conversion_rate`（入金コンバージョン率、0~1）を含む。

### 16.8 結合確率

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**レスポンス**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard 係数（両ゲームの共通プレイヤー / 和集合プレイヤー）と信頼度（共通プレイヤー / A ゲームのプレイヤー）。

### 16.9 リテンション分析

```
GET /admin/analytics/retention?days=30
```

**レスポンス**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` 登録日でグループ化した翌日/3日/7日/30日リテンション率。

### 16.10 コンバージョンファネル

```
GET /admin/analytics/funnel?days=30
```

**レスポンス**: 登録 → 初回入金 → 初回両替 → 初回ゲームプレイ の4ステップの `step`、`count`、`rate`（登録数に対するパーセンテージ）。

### 16.11 ARPU/ARPPU トレンド

```
GET /admin/analytics/arpu?days=30
```

**レスポンス**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` 日次のユーザー一人あたり売上（ARPU）と課金ユーザー一人あたり売上（ARPPU）。

### 16.12 ゲーム経済指標

```
GET /admin/analytics/economy
```

**レスポンス**: `currencies` 配列、各項目に `game_name`、`currency`、`symbol`、`total_minted`（鋳造総量）、`total_burned`（破棄総量）、`circulation`（流通量）、`inflation_rate`（インフレ率）を含み、bcmath 高精度計算を使用。
