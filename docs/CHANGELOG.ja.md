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
- モデルからハードコード `game_` プレフィックスを除去；DepositLog 監査を永続化；Test model 削除。

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

## [1.1] resilience — 2026-08-27

### 安定性

- 共有レイヤーに `CircuitBreaker`（Redis に状態保存、閾値 5 / ウィンドウ 30 秒、Redis 停止時 fail-open）と `Retry`（指数バックオフ、ネットワーク系例外のみ再試行、最大 5 回）を追加、`packages/platform-common/src/`。
- 縮退スイッチ `feature.provider_mock`：PushService（FCM/APNs/HarmonyOS）、PayoutService（PayPal）、ThirdPartyProvider が `on` 時にショートサーキットし、実ネットワーク呼び出しをスキップ。
- `getenv($name, '')` の第二引数型欠陥 11 箇所を修正（strict_types で TypeError）；PushService の mock チェックを try/catch 内へ移動。
- 新規テスト：CircuitBreakerTest / RetryTest / ResilienceMockTest；service スイート 45 → 60 ケース全て成功（報告: [test-reports/resilience.md](test-reports/resilience.md)）。

## [1.1] payments — 2026-08-29

- マルチ決済ゲートウェイ: Stripe Checkout / NOWPayments（USDT TRC20+ERC20）/ Coinbase Commerce（USDC）+ Alipay/WeChat Pay（Stripe Checkout APM）対応。
- 管理画面で決済手段 CRUD + 国別表示 + 金額範囲; チャージ注文作成時に checkout_url / expires_at を即時記録。
- 新マイグレーション install/migrations/2026_08_29_multi_payment.sql（実行が必要）。

## [1.1] cdn — 2026-08-29

- 5社 CDN 連携（Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS アップロード+パージ+プリロード）+ 管理画面設定（game_cdn_provider テーブル CRUD/有効無効/HeadBucket 疎通テスト）、service は DB のみ参照（config/cdn.php 削除）。

## [1.1] features — 2026-08-29

- ミニゲーム Farm Match-3 P0: ドメインエンジン + 4レベル設計 + Vitest 単体テスト（`game/xiaoxiaole/`）。
- ワンクリックインストールウィザード: ブラウザで管理者作成、既存 DB アップグレード（HY093 バインドパラメータ不一致、Unknown column 'countries' を修正）、install.lock で再インストール防止。
- CI: push 時に自動インクリメンタル tag + GitHub Release 公開。
- インフラ: データベースを game-platform に改名、`game_` テーブルプレフィックス統一。
- ドキュメント同期: FEATURES.md を 13 言語で補完（サーキットブレーカー/リトライ/縮退スイッチ）、決済手段 CRUD、ミニゲーム、ワンクリックインストール、CI 行（上記 [1.1] resilience / payments エントリに対応）。

## [1.1] reports — 2026-08-31

- データレポート：管理側 `/admin/report/summary|daily|export`（集計/日報/CSV エクスポート、Redis 5分キャッシュ、期間 ≤90日）。
- C端プラットフォーム統計：`GET /api/platform/stats`（ゲーム総数/ユーザー総数/今日の対局数/7日間アクティブ）、ホーム統計表示に接続。
- 管理側 Flutter：ダッシュボード統計カードを実データに接続、レポートページ ReportsPage（/reports）を新設。
- ドキュメント同期：FEATURES/VERSIONS/API にレポートと統計の項目を13言語で追記、機能全景図の統計分析ボックスを更新。
