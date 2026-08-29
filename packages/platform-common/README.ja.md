# erik/platform-common

## プロジェクトマスコット

<img src="../../docs/mascot.svg" width="120" alt="Dicey"/>

**ダイスィー（Dicey）** — プラットフォームのマスコット。サイコロはゲームと確率ベースのゲームプレイを、コインはプラットフォーム経済とマルチ決済ゲートウェイを、紫のメインカラーは管理画面ブランドを表します。SVG ファイル: `docs/mascot.svg`、文書・ロゴ・グッズに無限に拡大可能。
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

共有の `common\service\*` を、admin/ と service/ が Composer の path リポジトリ経由で参照します。

## サービス

- DepositLogService — チャージ監査 + 収益/転換
- GameDashboardService — 運営ダッシュボード
- ProbabilityService — 確率分析
- GamePlayLogService — ゲーム行動ログの書き込み

ホスト側が `app\model\*`、`app\common\SnowflakeService`、`support\Db`、`support\Log` を提供する必要があります。

## 導入

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## 残存する二重コピー

app/model/*、app/common/*Service、大多数の app/service/*、EventBus は引き続き両側に複製されています。

