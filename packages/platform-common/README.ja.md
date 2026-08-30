# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

admin/ と service/ が共有する `common\service\*` 共通レイヤー。Composer の path リポジトリでローカルソースを参照します。

## サービス

| サービス | 説明 |
|------|------|
| DepositLogService | 入金監査 + 収益/コンバージョン |
| GameDashboardService | 運営ダッシュボード |
| ProbabilityService | 確率分析 |
| GamePlayLogService | ゲーム行動ログの書き込み |
| CircuitBreaker / Retry | 安定性機構（サーキットブレーカー/リトライ） |

ホスト側が `app\model\*`、`app\common\SnowflakeService`、`support\Db`、`support\Log` を提供する必要があります。

## インストール

パッケージ名は `erik/platform-common`。admin/ と service/ はどちらも composer.json で path リポジトリ（`../packages/platform-common`）を設定済みのため、`composer install` で自動インストールされます。admin/ または service/ で個別に更新することもできます：

```bash
composer update erik/platform-common
```

Packagist に公開されていれば直接インストールも可能です：

```bash
composer require erik/platform-common
```

## 使い方

名前空間は `common\`（PSR-4 → `src/`）：

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## ワンクリックインストール

プラットフォームのワンクリックインストールウィザード（`install/`）で自動的に完了します：ウィザードが admin/ と service/ の `composer install` を実行し、path リポジトリの依存関係が自動インストールされるため、手動設定は不要です。

## 残存する二重コピー

`app/model/*`、`app/common/*Service`、大多数の `app/service/*`、EventBus は引き続き両側に複製されています。
