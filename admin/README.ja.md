# オープン管理画面 (open-admin)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**


webman v2 + Flutter ベースのフルスタック管理画面システム。

> [English version](README.en.md) | [アーキテクチャ設計図](docs/ARCHITECTURE.ja.md) | [設計ドキュメント](docs/DESIGN.ja.md) | [セキュリティアーキテクチャ](docs/SECURITY.ja.md) | [API リファレンス](docs/API.ja.md)

## 機能一覧

| 業務ドメイン | 機能 | 説明 |
|--------|------|------|
| 🔐 認証 | ログイン/登録/トークン更新/ログアウト | クリック型CAPTCHA + JWT + ブラックリスト |
| | アカウントロック | 5回失敗で15分間ロック |
| | 同時セッション制限 | 同一ユーザーの有効 Token は最大3つ |
| 📊 ダッシュボード | リアルタイム統計/トレンドグラフ/分布グラフ/最近の操作 | Redis キャッシュ5分 |
| 📈 データ分析 | 12エンドポイント：概要/ランキング/DAU/時間別/行動分布/売上/コンバージョン/確率/リテンション/ファネル/ARPU/経済指標 | MySQL リアルタイム集計、DB障害時は空データを返却 |
| 👥 ユーザー管理 | CRUD + 一括削除/有効・無効化 | ソフト削除 + パスワード再確認 |
| | Excel一括インポート | 行ごとの検証 + エラーレポート |
| 🔒 ロール権限 | ロールCRUD + 権限ツリー | RBAC method.path 粒度の認可 |
| ⚙ システム設定 | キー・バリューペアのCRUD | グループ管理 |
| 📋 操作監査 | ログ照会 + 送信元検出 | 8プラットフォームを自動識別 |
| 📁 ファイル管理 | アップロード/Excelエクスポート/PDFエクスポート | 機密データの自動マスキング |
| 🛡 セキュリティ保護 | 18層の多層防御 | XSS/SQLインジェクション/パストラバーサル/コマンドインジェクション/CSRF/レート制限/CSP... |
| 🏥 運用 | ヘルスチェック/metrics/APIドキュメント/security.txt | Prometheus + OpenAPI 3.0 |

## 技術スタック

| 層 | 技術 | 説明 |
|---|------|------|
| バックエンドフレームワーク | webman v2 (workerman) | 超高性能なPHP常駐プロセスフレームワーク |
| PHPバージョン | 8.3+ | |
| データベース | MySQL 8.0+ | テーブルプレフィックス `game_`、BIGINT 非オートインクリメント主キー |
| 検索エンジン | Elasticsearch | `webman-scout` で同期と検索 |
| 管理画面フロントエンド | Flutter 3.x | Web端はPC管理画面スタイル（`apps/flutter/`） |
| モバイル | HarmonyOS ArkTS | 鴻蒙(ホウモン)ネイティブクライアント（`apps/harmonyos/`）、スマホ/タブレット/2in1対応 |

## コア依存関係

| パッケージ | 用途 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake アルゴリズムでグローバル一意の BIGINT 主キーを生成 |
| `erikwang2013/hashids` | API層のID暗号化・復号、実際のデータベースIDを隠蔽 |
| `erikwang2013/jwt-webman` | JWT認証トークンの発行と検証 |
| `erikwang2013/encryption` | インターフェース転送層の機密データ暗号化・復号 |
| `erikwang2013/encryptable` | データベース保存層の機密フィールド自動暗号化・復号 |
| `erikwang2013/webman-scout` | Elasticsearch データ同期と全文検索 |
| `erikwang2013/season` | 国旗データ |
| `erikwang2013/poster-php` | クリック型CAPTCHAの生成と検証 + ポスター生成 |
| `phpoffice/phpspreadsheet` | Excel エクスポート |
| `barryvdh/laravel-dompdf` | PDF エクスポート（Dompdf ベース） |

## プロジェクト構成

```
open-admin/
├── app/
│   ├── admin/controller/       # 管理端コントローラー
│   │   ├── DashboardController.php # ダッシュボード（Redisキャッシュ）
│   │   ├── UserController.php      # ユーザー CRUD + 一括操作
│   │   ├── RoleController.php      # ロール CRUD
│   │   ├── PermissionController.php# 権限 CRUD
│   │   ├── ConfigController.php    # システム設定 CRUD
│   │   ├── LogController.php       # 操作ログ照会
│   │   ├── ProfileController.php   # 個人センター + ログアウト
│   │   ├── ExportController.php    # Excel/PDF エクスポート
│   │   ├── ImportController.php    # Excel によるユーザーインポート
│   │   ├── UploadController.php    # ファイルアップロード
│   │   ├── HealthController.php    # ヘルスチェック
│   │   ├── DocsController.php      # OpenAPI ドキュメント
│   │   └── BaseController.php      # ベースコントローラー
│   ├── api/
│   │   └── v1/controller/          # API v1 コントローラー（バージョンはリクエストヘッダー API-Version で制御）
│   │       ├── CaptchaController.php # クリック型CAPTCHA
│   │       └── AuthController.php    # ログイン/登録/トークン更新
│   ├── common/                 # 共通ユーティリティクラス
│   │   ├── HashidsService.php  # ID エンコード/デコード
│   │   ├── SnowflakeService.php# Snowflake ID 生成
│   │   └── EncryptionService.php # データ暗号化・復号 + マスキング
│   ├── middleware/             # 中間ウェア
│   │   ├── Cors.php            # クロスドメイン
│   │   ├── SecurityFilter.php  # 攻撃検知・遮断（HTTPメソッド制限/XSS/SQLインジェクション/パストラバーサル/コマンドインジェクション/CSRF）
│   │   ├── RateLimit.php       # Redis レート制限（スライディングウィンドウ + レスポンスヘッダー）
│   │   ├── ApiVersion.php      # API バージョン検証
│   │   ├── AdminAuth.php       # JWT 認証 + ブラックリスト
│   │   ├── AdminPermission.php # RBAC 権限検証
│   │   └── OperationLog.php    # 操作ログ自動記録（送信元検出を含む）
│   └── model/                  # データモデル
├── apps/
│   ├── flutter/                # Flutter Web 管理画面（PC スタイル）
│   │   └── lib/app/
│   │       ├── pages/          # 5つの完全なページ（ダッシュボード/ユーザー/ロール/設定/ログ/個人センター）
│   │       ├── services/       # ApiService（JWT インターセプター）+ AuthService（Token 永続化）
│   │       └── layouts/        # レスポンシブ管理画面レイアウト（サイドバー+トップバー+コンテンツ領域）
│   └── harmonyos/              # HarmonyOS ネイティブクライアント（Token シームレス更新）
├── config/                     # 設定ファイル（中国語コメント付き）
│   ├── route.php               # ルート + API バージョン戦略
│   ├── middleware.php           # グローバル中間ウェア登録
│   └── ...                     # 各コンポーネント設定
├── install/        # SQL 移行ファイル（権限シードデータ含む）
├── public/                     # 公開エントリー
├── runtime/                    # ランタイムファイル
└── vendor/                     # Composer 依存関係
```

## 環境要件

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41（フロントエンド開発のみ必要）
- Elasticsearch >= 7.x（任意、検索機能に必要）

## クイックスタート

### 1. 依存関係のインストール

```bash
composer install
```

### 2. 環境変数の設定

環境変数をコピーして変更します（任意。設定しない場合は `config/*.php` 内のデフォルト値を使用）:

```bash
cp .env.example .env
```

主要な設定項目：

| 環境変数 | 説明 | デフォルト値 |
|---------|------|--------|
| `JWT_SECRET` | JWT 署名キー | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids ソルト値 | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 暗号化キー | 32バイトのデフォルト値 |
| `SNOWFLAKE_DATACENTER_ID` | データセンターID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ワーカーノードID (0-31) | `1` |
| `SCOUT_HOSTS` | ES アドレス | `http://localhost:9200` |

**本番環境では必ずすべてのキーをランダム文字列に変更してください。**

### 3. データベース初期化

`install/` 配下のSQLファイルを順番に実行します：

```bash
mysql -u root -p < install/install.sql
```

### 4. サービスの起動

```bash
php start.php start
```

デフォルトでは `http://0.0.0.0:8787` をリッスンします。

### 5. フロントエンドの起動（任意）

**Flutter管理画面（Web端）:**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web端（PC管理画面スタイル）
```

**HarmonyOSクライアント（スマホ端）:**

DevEco Studio で `apps/harmonyos/` ディレクトリを開き、実機またはエミュレーターに接続して実行します。

### 6. Docker Compose によるワンクリックデプロイ（本番環境推奨）

プロジェクトには5つのサービス（Nginx、PHP (webman app)、MySQL、Redis、Elasticsearch）を含む完全な Docker オーケストレーション構成が用意されています。

```bash
# 1. Docker 環境変数の設定
cp .env.docker .env

# 2. 全サービスの起動
docker-compose up -d

# 3. データベースの初期化（app コンテナ内で実行）
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. アクセス
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx リバースプロキシ)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer、`php:8.3-cli` ベース
- `docker-compose.yml`: 5サービスのオーケストレーション、ネットワーク分離、データボリューム永続化
- `.env.docker`: Docker環境専用の環境変数


## データベース規約

- **テーブルプレフィックス**: `game_`
- **主キー**: 全テーブルの主キーは `id BIGINT UNSIGNED NOT NULL`、**AUTO_INCREMENT は禁止**
- **ID生成**: 主キーIDはアプリケーション層の `SnowflakeService::generate()` で生成され、分散環境で一意
- **必須フィールド**: 各テーブルに `id`, `created_at`, `updated_at` が必須
- **ソフト削除**: ソフト削除が必要なテーブルには `deleted_at DATETIME DEFAULT NULL` を追加
- **機密フィールド**: 携帯番号、メールアドレス、身分証番号などは `encryptable` プラグインで自動暗号化・復号し、データベースフィールドは `VARCHAR(500)` で暗号文を保存

## API 規約

### 統一レスポンス形式

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### ビジネスエラーコード

| エラーコード | 意味 | 説明 |
|-------|------|------|
| `0` | 成功 | |
| `400` | リクエストパラメータエラー | |
| `401` | 未ログイン（Token が無効または期限切れ） | |
| `403` | 権限なし / セキュリティ遮断 | RBAC認可失敗 / SecurityFilter 攻撃検出 |
| `404` | リソースが存在しない | |
| `422` | パラメータ検証失敗 | |
| `413` | リクエストボディが大きすぎる | SecurityFilter 発動、10MB 超過 |
| `405` | リクエストメソッドが許可されていない | SecurityFilter 発動、GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可 |
| `415` | サポートされていないメディアタイプ | SecurityFilter 発動、Content-Type が JSON 以外 |
| `429` | リクエストが多すぎる | RateLimit 発動 / アカウントロック（ログイン5回失敗で15分間ロック） |
| `500` | サーバー内部エラー | |

### ID の取り扱い

- **リクエスト/レスポンス内の ID**: hashids で文字列に暗号化し、実際のデータベースIDを公開しない
- **APIパス**: `GET /admin/user/{hashid}` — パス内の `{id}` は hashid 文字列
- **データベース保存**: BIGINT の原値、snowflake で生成

### API バージョン

APIバージョンはリクエストヘッダーで制御され、**URLには含まれません**：

```http
API-Version: v1
```

- バージョン番号がない場合はデフォルトで `v1` を使用
- サポートされていないバージョンは `400 Bad Request` を返す
- 新しいバージョンを追加する場合は `app/api/{version}/controller/` ディレクトリを作成し、中間ウェアに新しいバージョンを登録するだけ

### レート制限

Redis スライディングウィンドウアルゴリズムに基づき、デフォルトは 60 回/分/IP/ルート。機密性の高いAPIはより厳格：
- ログイン：10 回/分
- 登録：5 回/分

レスポンスヘッダーには `X-RateLimit-Limit`、`X-RateLimit-Remaining`、`X-RateLimit-Reset` が含まれます。超過時は 429 を返し、`Retry-After` を添付します。

### 中間ウェアアーキテクチャ

グローバル中間ウェアはすべてのリクエストに適用され、順番に実行されます：

```
Cors（クロスドメイン前処理 + レスポンスヘッダー）
  → SecurityFilter（HTTPメソッド制限/リクエストボディサイズ/Content-Type検証/XSS/SQLインジェクション/パストラバーサル/コマンドインジェクション/CSRF 攻撃遮断）
  → RateLimit（Redis スライディングウィンドウレート制限 + アカウントロック：ログイン5回失敗で15分間ロック）
  → ApiVersion（API バージョン検証、/api ルートグループ）
  → AdminAuth（JWT 認証 + ブラックリスト、/admin ルートグループ）
  → AdminPermission（RBAC 認可、/admin ルートグループ）
  → OperationLog（POST/PUT/DELETE 自動記録、送信元検出含む、/admin ルートグループ）
```

`/health` と `/api/docs` は公開エンドポイントで、`Cors → SecurityFilter → RateLimit` のみを通過します。

セキュリティ強化：
- **アカウントロック**：ログイン連続5回失敗でアカウントが自動的に15分間ロックされ、その間のログインは 429 を返す
- **同時セッション制限**：同一ユーザーの有効 Token は最大3つ、超過時は最も古い Token が自動的にブラックリスト入り
- **security.txt**：`GET /.well-known/security.txt` で RFC 9116 標準のセキュリティ連絡先情報を提供
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` を参照し、完全なリバースプロキシセキュリティ強化のサンプルを提供

### 認証

ログインと登録はまず**クリック型CAPTCHA**の検証を通過する必要があります：

1. クライアントは `POST /api/captcha/generate` をリクエストしてCAPTCHA画像（base64 PNG）と文字ターゲットのリストを取得
2. ユーザーが画像内の対応する文字位置を順番にクリックし、クリック座標 `[{x, y}, ...]` を収集
3. ログイン時に `captcha_key` と `clicks` を一緒に送信し、サーバー側はCAPTCHAを検証してから資格情報を検証

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

管理画面の以降のAPIには JWT 認証が必要です：

```http
Authorization: Bearer <token>
```

ログイン成功後、有効期限2時間の access_token が返されます。さらに、有効期限14日の refresh_token も返されます。

ログアウト時、Token は Redis ブラックリストに追加され、有効期限内は再利用できません。POST /admin/profile/logout

### 機密操作の再確認

ユーザー・ロール・権限の削除などの機密操作では、リクエストボディに現在ログイン中のユーザーの `password` を渡して本人確認を再度行う必要があります：

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 一覧

> すべての `/api/*` API はリクエストヘッダーに `API-Version: v1` を付ける必要があります（未指定時はデフォルトで v1）。

### 公開API

| メソッド | パス | 説明 |
|-----|------|------|
| `GET` | `/health` | ヘルスチェック（DB/Redis/ES の状態） |
| `GET` | `/api/docs` | OpenAPI 3.0 仕様ドキュメント |
| `POST` | `/api/captcha/generate` | クリック型CAPTCHAの生成 |
| `POST` | `/api/captcha/verify` | クリック型CAPTCHAの検証 |
| `POST` | `/api/auth/login` | ログイン（captcha 必須） |
| `POST` | `/api/auth/register` | 登録（captcha 必須） |
| `POST` | `/api/auth/refresh` | トークン更新 |
| `GET` | `/metrics` | Prometheus 監視メトリクス |

### 管理画面API（JWT + RBAC 必須）

| メソッド | パス | 説明 |
|-----|------|------|
| `GET` | `/admin/dashboard` | ダッシュボードデータ（Redis キャッシュ5分） |
| `GET` | `/admin/user` | ユーザー一覧（ページング + 検索） |
| `POST` | `/admin/user` | ユーザー作成 |
| `GET` | `/admin/user/{id}` | ユーザー詳細 |
| `PUT` | `/admin/user/{id}` | ユーザー更新 |
| `DELETE` | `/admin/user/{id}` | ユーザー削除（ソフト削除、パスワード確認が必要） |
| `POST` | `/admin/user/batch/destroy` | ユーザーの一括削除（パスワード確認が必要） |
| `POST` | `/admin/user/batch/status` | ユーザーの一括有効化/無効化 |
| `GET` | `/admin/role` | ロール一覧 |
| `POST` | `/admin/role` | ロール作成 |
| `PUT` | `/admin/role/{id}` | ロール更新 |
| `DELETE` | `/admin/role/{id}` | ロール削除（パスワード確認が必要） |
| `GET` | `/admin/permission` | 権限ツリー |
| `POST` | `/admin/permission` | 権限作成 |
| `PUT` | `/admin/permission/{id}` | 権限更新 |
| `DELETE` | `/admin/permission/{id}` | 権限削除（子権限をカスケード削除、パスワード確認が必要） |
| `GET` | `/admin/config` | システム設定一覧 |
| `POST` | `/admin/config` | 設定項目の作成 |
| `PUT` | `/admin/config/{id}` | 設定項目の更新 |
| `DELETE` | `/admin/config/{id}` | 設定項目の削除（パスワード確認が必要） |
| `GET` | `/admin/log` | 操作ログ（ページング + フィルタリング） |
| `PUT` | `/admin/profile` | 個人情報の更新 |
| `PUT` | `/admin/profile/password` | パスワード変更 |
| `POST` | `/admin/profile/logout` | ログアウト（JWT ブラックリスト） |
| `POST` | `/admin/export/excel` | Excel エクスポート |
| `POST` | `/admin/export/pdf` | PDF エクスポート |
| `POST` | `/admin/import/users` | Excel によるユーザーインポート |
| `POST` | `/admin/upload` | ファイルアップロード（画像/ドキュメント、最大10MB） |

## フロントエンドについて

### Flutter 管理画面（PC スタイル）

- **レイアウト**: サイドバー（折りたたみ可能 64px/240px）+ トップバー + コンテンツ領域、レスポンシブ3ブレークポイント（スマホ/タブレット/デスクトップ）
- **ページ**: ログイン、ダッシュボード、ユーザー管理、ロール権限、システム設定、操作ログ、個人センター
- **状態管理**: GetX（`ApiService` シングルトン + `AuthService` Token 永続化）
- **ダッシュボード**: 統計カード、トレンド折れ線グラフ（fl_chart）、円グラフ、最近の操作ログ
- **エクスポート**: Excel/PDF エクスポート、PDF には削除できない著作権情報が含まれる
- **一括操作**: 複数選択による一括削除、一括有効化/無効化
- **テーマ**: Material 3 ライト/ダークの2テーマ

### HarmonyOS モバイル

- **ページ**: ログイン、ダッシュボード、ユーザー一覧/詳細、個人センター
- **認証**: JWT Bearer + 401時に自動でシームレスに Token を更新、更新失敗時はログインページへ自動リダイレクト
- **保存**: Token は AppStorage で管理

## 開発規約

- グローバル関数/クラスの参照に前置 `\` を付けず、統一して `use` でインポート
- すべての PHP ファイルの先頭に著作権表示を含める必要がある
- すべての設定ファイルに中国語コメントの説明を含める必要がある
- データベースの主キーはアプリケーション層の snowflake で生成し、オートインクリメントは禁止
- API層のすべてのパラメータとレスポンス内の ID は hashids で暗号化・復号する必要がある
- AdminPermission 中間ウェアは Redis でユーザー権限をキャッシュ（TTL=60秒）し、N+1 クエリのボトルネックを解消

## デプロイ

### Docker Compose（推奨）

プロジェクトルートに `docker-compose.yml` があり、5つのサービスを構成：

| サービス | イメージ | ポート |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | ローカル `Dockerfile` でビルド | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP イメージは `Dockerfile` で構築され、ベースイメージは `php:8.3-cli`、OPcache を有効化。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions 継続的インテグレーションパイプライン：`.github/workflows/ci.yml`

- PHP 構文チェック (`php -l`)
- PHPUnit ユニットテスト
- Flutter 静的解析 (`flutter analyze`)

### データベースバックアップ

`database/backup/` ディレクトリ：

- `backup.sh` — mysqldump + gzip バックアップ、30日前の古いバックアップを自動削除
- `restore.sh` — 対話式リストア、利用可能なバックアップを一覧表示して選択

### Nginx セキュリティ設定

本番デプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティを強化してください。

## オープンソースの継続にご支援を

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
