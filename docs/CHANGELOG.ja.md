# Changelog
<!-- lang-nav -->

Languages: **中文** · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

人間が読める変更記録。PHP は本ファイルを import しない。対応する PROJECT-PLAN P2-21。

## [1.1] — 2026-08-07

- Redis プラグイン接続、分析サービス、Redis ダウングレード、テスト修正。

## [1.1] security / ops — 2026-08-18

### セキュリティ

- 決済コールバック: provider ホワイトリスト（stripe/paypal）、fail-closed 署名検証、金額照合、入金のトランザクション化、Stripe タイムスタンプ ±300s でリプレイ防止。
- JWT: `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` が欠落またはデフォルト値の場合に起動拒否。
- Apple id_token: JWKS（RS256）署名検証 + aud/iss/exp。
- Webhook: https 公網 URL のみ、内網/予約アドレスを拒否（SSRF）。
- 2FA: TOTP HMAC は RFC 4648 Base32 デコード後の鍵を使用；`/api/2fa/verify` はユーザーごとの失敗ロック（5 回 / 15 分、Redis 障害時 fail-closed）。
- 出金: 審査/送金の条件 UPDATE で状態を原子的に切替；任意の二重審査（`withdraw.require_dual_review`）；申請側の Redis ユーザーロックで限度額の並行突破を防止。
- レートリミット: Redis 障害時 fail-closed。

### 可用性

- admin 分析サービスの 12 本の `/admin/analytics/*` ルートをマウント。
- モデルからハードコード `erik_` プレフィックスを除去；DepositLog 監査を永続化；Test model 削除。

### 可観測性

- `GET /metrics` に審査待ち出金、本日確定チャージ（COUNT クエリ Redis 30s キャッシュ）、イベント emit/consume カウント、`memory_usage`、`info version=1.1` を追加。
- FeatureFlag: `inRollout` / `abTest` が crc32 バケットで `feature.{name}_percent` を参照。
- EventBus の `emit` / `consume` が Redis `metrics:event_emit_total` / `metrics:event_consume_total` を INCR。

### クライアント / 共有（同日に補完）

- Flutter Platform: `app_pages.dart` ルートテーブル；2FA 設定/検証、クーポン、ランキング、通知、OAuth コールバックページを補完；ロビーのエントリをナビゲーションに接続。
- HarmonyOS C 端: `apps/harmonyos/` の 5 ページ（ログイン/ロビー/詳細/ウォレット/個人）、デフォルト `BASE_URL` は service `8788` を指す。
- 共有レイヤー: `packages/platform-common`（`erik/platform-common` path repo）に DepositLog / GameDashboard / Probability / GamePlayLog を抽出；model は依然二重。
- ClickHouse: composer 依存を除去；分析は引き続き MySQL リアルタイム集計。
- CI: admin / service を分けた job で phpunit を実行、失敗で即ブロック。

### 依然残るギャップ

- admin/service **モデル**は依然二重（`common/service` の一部のみ path パッケージ化）。
- `webman/queue` 未接続；確率/リテンションは OLAP に未移行。
- PROJECT-PLAN / VERSIONS / 監査レポートの一部段落は本 CHANGELOG より遅れている可能性がある。本ファイルとディスク上の実態を正とする。
