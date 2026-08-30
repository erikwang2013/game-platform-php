# game_platform — ユーザープラットフォーム（Flutter Web）
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C端ユーザープラットフォームの Web フロントエンド。Flutter 3.x ベースで、ユーザー向けにゲーム集約プラットフォームの完全な体験（登録・ログイン、ゲームロビー、ウォレット、入金、出金、両替、ランキング、クーポン、通知、チャット、友達、サポートチケット）を提供します。

## 機能一覧

| モジュール | 説明 |
|------|------|
| ログイン/登録 | ユーザー名・パスワード / OAuth / 2FA |
| ゲームロビー | ゲーム一覧/カテゴリ/検索 |
| ウォレット | プラットフォームコイン/ゲームコイン残高と取引履歴 |
| 入金 | 支払い方法を選択し、ゲートウェイ決済へ遷移 |
| 出金 | 出金申請、審査状況の追跡 |
| 両替 | プラットフォームコイン ⇄ ゲームコインのリアルタイム両替 |
| ランキング | 日/週/月/総合 |
| クーポン | 受け取りと利用 |
| 通知 | アプリ内メッセージ（入金/出金/クーポン等） |
| チャット | WebSocket リアルタイムメッセージ |
| 友達 | 友達システム |
| チケット | サポートチケットの作成と返信 |
| プロフィール | プロフィール編集/セキュリティ設定 |

## 環境要件

- Flutter SDK 3.x

## インストールと実行

```bash
cd apps/flutter/platform

# 依存関係をインストール
flutter pub get

# 開発実行（Chrome）
flutter run -d chrome

# バックエンドのアドレスを指定（デフォルト http://localhost:8788）
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Web 本番ビルド（出力先 build/web/）
flutter build web
```

## 使い方

1. 先にバックエンドを起動：`cd service && php start.php start -d`（デフォルトポート 8788）
2. アカウントを登録してログイン（ユーザー名・パスワード、OAuth、2FA に対応）
3. 入金後、プラットフォームコインでゲームをプレイし、ゲームコインに両替できます。ゲームコインはウォレットに戻して出金も可能です
4. 管理バックエンドは `admin/` ディレクトリ（Flutter Web フロントエンド `admin/apps/flutter/` 含む）
