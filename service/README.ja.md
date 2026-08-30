# service/ — C端用户平台 API サービス
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C端ユーザープラットフォーム API サービス。webman v2（Workerman）ベースの高性能 PHP バックエンドで、ユーザー向けにゲーム集約プラットフォームの完全な機能（会員登録・ログイン、ウォレット、入金、出金、両替、ゲーム、ランキング、クーポン、サポートチケット、VIP、実績、ソーシャル、お知らせ）を提供します。

## 機能一覧

| モジュール | 説明 |
|------|------|
| ユーザー | 登録/ログイン（ユーザー名・パスワード + 7 プラットフォーム OAuth + 2FA TOTP）、プロフィール |
| ウォレット | プラットフォームコインウォレット（楽観ロック）+ ゲームコインウォレット + 取引履歴 |
| 入金 | 13 ゲートウェイ（Stripe/PayPal/NowPayments/Coinbase 等）のコールバック署名検証と自動入金 |
| 出金 | 申請 → 審査 → 支払い、KYC 段階別限度額 |
| 両替 | プラットフォームコイン ⇄ ゲームコインのリアルタイム見積、VIP 割引とレート上乗せ |
| ゲーム | ゲーム一覧/カテゴリ/検索、プレイ履歴、Provider 決済コールバック |
| ランキング | 日/週/月/総合 + WebSocket リアルタイム配信 |
| クーポン | 固定金額 + 比率割引、期間・数量限定 |
| チケット | ユーザーによるサポートチケットの作成/返信 |
| VIP | 5 段階ロイヤルティ、経験値累積、両替割引 |
| 実績 | 内蔵 12 実績、イベント駆動検出 |
| ソーシャル | 友達システム + WebSocket リアルタイムメッセージ |
| お知らせ | アプリ内お知らせ + 通知/メール |

## 技術スタック

- PHP 8.3+ / webman v2（workerman/webman）
- MySQL 8.0+（テーブル接頭辞 `game_`、BIGINT 非自動採番主キー）
- Redis（Session / キャッシュ / レート制限）
- ClickHouse（OLAP 分析 / 確率計算）
- Elasticsearch（全文検索）
- JWT 認証 + HMAC-SHA256 Provider 署名

## プロジェクト構成

```
service/
├── app/
│   ├── api/v1/controller/  # C端 API コントローラー（35 個）
│   ├── middleware/         # ミドルウェア（Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth）
│   ├── model/              # データモデル
│   ├── service/            # ビジネスサービス（VIP/ランキング/リスク/通知等）
│   ├── event/              # イベントバス（EventBus Redis Pub/Sub）
│   ├── provider/           # ゲーム Provider 層
│   └── payment/            # 決済ゲートウェイ
├── common/                 # 共有サービスディレクトリ（実体は erik/platform-common パッケージ）
├── config/                 # 設定ファイル
├── public/                 # Web エントリ
├── tests/                  # PHPUnit テスト
├── start.php               # 起動エントリ
└── composer.json
```

## ワンクリックインストール

プロジェクトルートのワンクリックインストールウィザードを推奨します（プロジェクトルートで実行）：

```bash
# 1. インストールウィザードを起動
php -S 0.0.0.0:8888 -t install/

# 2. ブラウザで http://localhost:8888 を開く
#    ウィザードに従う：環境チェック → データベース設定 → 管理者アカウント作成 → 自動インストール
```

または Docker Compose で一括起動（プロジェクトルート）：

```bash
docker compose up -d
```

## 手動インストール

```bash
# 1. 依存関係をインストール
cd service && composer install

# 2. 環境変数を設定
cp .env.example .env
# .env を編集：データベース接続情報、JWT キー等

# 3. サービスを起動（デフォルトポート 8788）
php start.php start        # フォアグラウンド
php start.php start -d     # バックグラウンド
```

## 使い方

- API ドキュメント：`docs/API.md`（完全な API リファレンス）
- オンラインドキュメント：http://localhost:8788/apidoc/（hg/apidoc 対話型ドキュメント）
- ヘルスチェック：`GET http://localhost:8788/health`
- C端フロントエンド：`apps/flutter/platform/`（Flutter Web ユーザープラットフォーム）
- 管理バックエンド：`admin/`（管理バックエンドと `admin/apps/flutter/` フロントエンド）

## テスト

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
