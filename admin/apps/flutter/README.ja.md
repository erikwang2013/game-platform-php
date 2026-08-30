# admin_app — 管理后台 Web フロントエンド（Flutter）
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Flutter 3.x ベースの管理后台 Web フロントエンド。PC 向けの定番管理画面レイアウト（サイドバー + トップバー + コンテンツ領域）を採用し、ゲームプラットフォームの運営に必要なすべての管理ページをカバーします：ダッシュボード、ユーザー、ロール権限、ゲーム、支払い、出金、VIP、実績、お知らせ、CDN、リスク管理、実名認証、操作ログなど。

## 機能一覧

| モジュール | 説明 |
|------|------|
| ダッシュボード | プラットフォーム運営データの総覧 |
| レポート | データレポート集計/日報/CSV 出力 |

| ログイン | 管理者ログイン（2FA 対応） |
| ユーザー管理 | プラットフォームユーザーの検索・管理 |
| プラットフォームユーザー | ユーザー明細・状態・残高操作 |
| ロール権限 | ロールと権限の割り当て |
| システム設定 | プラットフォームパラメータの設定 |
| ゲーム管理 | ゲーム一覧・公開停止・カテゴリ |
| 支払い管理 | 入金注文・支払い方法・コールバックログ |
| 出金管理 | 出金審査と支払い実行 |
| VIP 管理 | VIP レベルと特典の設定 |
| 実績管理 | 実績定義と進捗の確認 |
| お知らせ管理 | お知らせの公開・停止 |
| CDN 管理 | CDN 各社の設定とドメイン管理 |
| リスク管理 | リスクルールと遮断記録 |
| 実名認証 | 実名情報の審査 |
| 操作ログ | 管理者操作の監査ログ |
| 個人センター | 管理者プロフィールとセキュリティ設定 |

## 環境要件

- Flutter SDK 3.x

## インストールと実行

```bash
cd admin/apps/flutter

# 依存関係のインストール
flutter pub get

# 開発実行（Chrome）
flutter run -d chrome

# バックエンドのアドレスを指定（デフォルト http://localhost:8787）
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Web 本番ビルド（出力先 build/web/）
flutter build web
```

## 使い方

1. 先に管理后台バックエンドを起動：`cd admin && php start.php start -d`（デフォルトポート 8787）
2. インストールウィザードで作成した管理者アカウントでログイン（2FA 対応）
3. ユーザー向けフロントエンドは `apps/flutter/platform/` にあり、同じバックエンドサービス（デフォルトポート 8788）を利用します
