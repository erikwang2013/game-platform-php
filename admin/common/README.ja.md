# common/ — 管理后台共通ライブラリ
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · **日本語**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

管理バックエンド（admin/）の共通コードディレクトリ。`common\service\*` は共有パッケージ **erik/platform-common**（`packages/platform-common`）に抽出済みのため、本ディレクトリには PHP クラスを置かないでください（パッケージの自動ロードを遮蔽します）。詳細は `packages/platform-common/README.md` を参照してください。

## 機能説明

| カテゴリ | 場所 | 説明 |
|------|------|------|
| モデル | `app\model\*` | データモデル（ユーザー/注文/ゲーム等） |
| サービス | `common\service\*` | 共有ビジネスサービス（erik/platform-common パッケージ内）：DepositLogService（入金監査+収益/コンバージョン）、GameDashboardService（運営ダッシュボード）、ProbabilityService（確率分析）、GamePlayLogService（ゲーム行動ログ書き込み） |
| ミドルウェア | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## インストール

admin プロジェクトの一部として依存関係は `admin/composer.json` に宣言済み（path リポジトリ `../packages/platform-common` を含む）で、`composer install` 時に自動インストールされます。個別のインストールは不要です：

```bash
cd admin && composer install
```

## 使い方

- `app\...` 名前空間は admin プロジェクト自身のコードに対応します。例：`use app\model\User;`
- `common\...` 名前空間は共有パッケージ erik/platform-common（PSR-4 → `src/`）に対応します。例：

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
