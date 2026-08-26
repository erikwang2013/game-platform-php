# プロジェクト全体計画 (Project Plan)
<!-- lang-nav -->

Languages: **中文** · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> 生成日: 2026-08-16 · 6 人チーム (researcher/architect/backend-dev/frontend-dev/tester/reviewer) による読み取り専用棚卸し + 重要指摘の実測検証に基づく
> 対象: 現状まとめ / 問題とリスク / P0-P1-P2 ロードマップ / ドキュメント修正 / 品質ゲート

---

## 一、プロジェクト現状

**グローバルゲームアグリゲーションプラットフォーム** — PHP 8.3 + webman v2、デュアルアプリケーション monorepo:
`admin/`(8787 管理バックエンド) + `service/`(8788 C端) + `apps/`(Flutter + HarmonyOS) + `install/`(インストールウィザード 43 テーブル)。

| 観点 | 実測規模 |
|------|---------|
| コントローラー | admin 32 + service 30 = 62 |
| API エンドポイント | ~149 (admin 103 / service 88、Webhook/Provider コールバック含む) |
| データモデル | admin 46 / service 44、admin/service **重複コピー** (共有レイヤーなし) |
| テスト | 132 ケース / 8 ファイル (admin プロジェクト)、service プロジェクトは **ゼロテスト** |
| バージョン | v1.1 (2026-08-07)：Redis プラグイン、分析サービス、Redis ダウングレード、テスト修正 |

実装済み機能: JWT+RBAC、ウォレット楽観ロック、チャージ(Stripe/PayPal 署名検証)、交換差益、出金審査+PayPal 送金、ゲーム CRUD/Provider ゲートウェイ(HMAC)、クーポン/VIP/アチーブメント/チケット/紹介報酬/2FA/ソーシャル(友達/チャット WS)/トーナメント/Webhook/プッシュ(FCM/APNs/华为)/i18n バイリンガル。

---

## 二、問題とリスク（実測検証済み）

### CRITICAL — 資金セキュリティ

| # | 問題 | 場所 |
|---|------|------|
| C1 | 決済コールバックの `provider` がクライアントから渡され、stripe/paypal 以外の場合は **署名検証を完全にスキップ**、偽造コールバックで直接入金される | service/.../PaymentController.php:36-42 |
| C2 | 署名検証 fail-open：`STRIPE_WEBHOOK_SECRET` 未設定 → `return true`；PayPal の例外 → `return true`。攻撃チェーン: 自前チャージ注文→偽造コールバック→無限チャージ | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` がデフォルトで公開ハードコード鍵 `open-admin-jwt-secret-change-in-production` にフォールバック、本番で env 未設定なら管理者 Token を偽造可能 | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — 正確性/整合性

| # | 問題 | 場所 |
|---|------|------|
| H1 | 分析サービス AnalyticsController の 12 メソッドはすべて実装済みだが **ゼロルーティング**、すべて 404 のデッドコード、VERSIONS.md は納品済みと主張 | admin/config/route.php (0 箇所 analytics) |
| H2 | イベントバス断線: emit は 4 箇所で呼び出し(game.played/withdraw.completed/exchange.completed/referral.applied)、`subscribe()` にはどのプロセスも登録されておらず、イベントは発行されると失われる；VIP/アチーブメント/通知エンジンはすべて宙に浮いた状態 | admin+service app/event/EventBus.php |
| H3 | common/ と model/ が二重コピーされ既にドリフト（DepositLogService が 2 ファイルで内容が異なる、User.php も不一致）、単点修正が二重作業になる。**common/service は抽出済み** `packages/platform-common`（erik/platform-common、旧 common-php は統合済み）；model と app/common ラッパーは依然二重 | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C端 `apps/harmonyos/` は空ディレクトリ、0 ページ vs VERSIONS.md は 5 ページと主張~~ — 実装済み（2026-08-18：5 ページ実装が `apps/harmonyos/` にある） | apps/harmonyos/ |
| H5 | Stripe コールバックが `t=` タイムスタンプ許容差を検証せず（リプレイ可能）、入金金額もゲートウェイの実支払額と照合されない | PaymentController.php:191-194 |
| H6 | Apple id_token は payload を base64 デコードするのみ、署名検証・aud/iss/exp 検証なし、アプリ間の身元混同リスク | OAuthController.php:376-380 |

### MEDIUM — 信頼性/実装上の欠陥

| # | 問題 |
|---|------|
| M1 | 2FA の欠陥が二重: `/api/2fa/verify` は公開でユーザーごとの試行ロックなし（ブルートフォース oracle）；TOTP が Base32 文字列をそのまま HMAC 鍵として使用（デコードなし）、Authenticator と一致しない → **2FA は実質利用不可** |
| M2 | 出金審査/送金が check-then-act で原子的な状態更新がなく、並行時に重複送金の可能性；二重審査なし |
| M3 | Webhook コールバック URL は filter_var 検証のみ、内網 IP を指定可能（SSRF）、dispatch が任意 URL に POST |
| M4 | 出金の日/月限度額が「先に確認してから挿入」で非原子的、並行時に限度を突破可能 |
| M5 | Redis 障害時 fail-open に統一抽象がない: JWT ブラックリストの失効が無効化、レートリミットが静かに無効化；ダウングレードの穴: PayoutService::getAccessToken、ChatWebSocket brpop、OAuth state の読み書き |
| M6 | ClickHouse はゼロ利用: 確率計算は実質 MySQL リアルタイム COUNT(DISTINCT)+サブクエリ JOIN、大テーブルで O(n²) リスク；composer は依存の占いで能力なし |
| M7 | キューの半製品: admin/app/queue に ComputeDailyStats + 3 つの ES タスクがあるが、webman/queue 未インストール、process.php に登録なし、呼び出し元ゼロ |
| M8 | デッドコード: Vip/Achievement/Notification/FeatureFlag サービスに呼び出し元ゼロ；DepositLogService::log() は空実装；Test model が残存；リテンションアルゴリズムは単一コホートの粗い推算 |

### LOW
- 出金に 2FA/KYC の強制がなく、任意の PayPal メールに送金可能；審査メモが通知文言に入る（XSS 面）
- ドキュメントと実態が不一致: install.sql 43 テーブル vs ドキュメントはかつて 52 と記載；docker-compose 7 サービス vs FEATURES.md はかつて 8 と記載；「共有 Model 34」は不実（admin 46 / service 44 が各 1 部、共有レイヤーなし）。CHANGELOG は補完済み、`docs/CHANGELOG.md` 参照。

### 合格項目（セキュリティ審査で問題なし確認）
ウォレット楽観ロック+バージョン条件更新は正確；コールバックの冪等 `where status=pending` 条件更新は正確；全 ORM で直接 SQL 組み立てなし；.env は git 未収録；admin の全ルートが AdminAuth+RBAC デフォルト拒否；OAuth state 検証+単回消費は正確。

> **2026-08-18 修正状況**: C1/C2/C3/H1/H5/H6 修正済み；H2 イベントバス: `process.php` に `event-consumer` が登録され、消費クラス `EventConsumer` が実装済み、emit に消費者あり；M1 Base32 + ユーザーごとのロック修正済み；M2 出金状態の原子化 + 任意の二重審査実装済み；M3 Webhook SSRF 遮断済み；M4 出金申請の Redis ユーザーロック実装済み；M5 一部完了（RateLimit fail-closed）；P2-19 業務指標 + FeatureFlag グレースケール実装済み。問題リストは歴史的監査結論として維持。

---

## 三、ロードマップ

### P0 — 資金セキュリティ + 正確性（最優先、リリースをブロック）

1. **決済コールバック fail-closed**: provider ホワイトリスト（stripe/paypal のみ）+ 鍵欠落時は 500 拒否 + PayPal 例外は必ず拒否（C1/C2） — ✅ 完了済み（2026-08-18: provider ホワイトリスト + チャネル横断のすり替え検証 + 送信元 IP 任意検証 + コールバック入金のトランザクション化）
2. **JWT 起動時検証**: env に `JWT_SECRET_KEY` がない場合は起動拒否（C3） — ✅ 完了済み（2026-08-18: JWT_SECRET_KEY 欠落またはデフォルト値 `open-admin-jwt-secret-change-in-production` の場合に起動拒否、admin/service 一致）
3. **分析サービスのルート登録**: analytics 12 ルート + 権限ポイントを登録、VERSIONS.md の約束を修正（H1） — ✅ 完了済み（2026-08-18: admin/config/route.php に 12 本の `/admin/analytics/*` ルートを登録）
4. **イベントバス接続**: 常駐サブスクライブプロセスを登録して消費、または同期直接呼び出しに変更；イベントの永続化 + 失敗リトライ（H2） — ✅ 完了済み（2026-08-18: emit/consume が Redis カウンターを INCR；`service/config/process.php` に `event-consumer` を登録、`service/app/process/EventConsumer.php` がイベントを消費）
5. **Apple id_token 署名検証**: JWKS 検証 + aud/iss/exp（H6） — ✅ 完了済み（2026-08-18: RS256 JWKS + kid リフレッシュ + aud/iss/exp）
6. **Stripe リプレイと金額照合**: タイムスタンプ許容差 + ゲートウェイ金額との比較（H5） — ✅ 完了済み（2026-08-18: t= タイムスタンプ ±300s でリプレイ防止 + bccomp 精度の金額照合 + secret/webhook_id 未設定または署名検証例外は一律拒否）

### P1 — 信頼性 + 整合性

7. **共有レイヤーの重複排除**: common/model を composer path repo（またはシンボリックリンク）に抽出、二重ドリフト解消（H3） — 🔶 一部完了（2026-08-18: `common/service` は単一の `packages/platform-common` / `erik/platform-common` path repo に抽出済み（旧 `common-php` は統合済み）、admin+service が参照；model と host に縛られた `app/common` ラッパーは依然二重、`packages/platform-common/DUAL_MODELS.md` 参照）
8. **統一 Redis ダウングレードラッパー**: fail ポリシーを明示化 + 警告を無音にしない；PayoutService/OAuth/ChatWebSocket のフォールバック補完（M5） — 🔶 一部完了（RateLimit fail-closed 実装済み: Redis 障害時はレートリミット拒否、静かな通過ではない；その他は未実施）
9. **webman/queue 接続**: イベントと webhook 配信を担う（消費リトライ、デッドレター）、ComputeDailyStats/ES タスクを有効化または削除（M7） — ⬜ 未実施
10. **2FA 修正**: Base32 デコード + verify にログイン状態とユーザーごとの試行ロックを追加（M1） — ✅ 完了済み（2026-08-18: RFC 4648 Base32 デコード後に HMAC；`/api/2fa/verify` は 5 回失敗で 15 分ロック、Redis 障害時 fail-closed）
11. **出金の原子化**: 審査/送金の条件更新 + 二重審査；限度額の Redis Lua/一意制約（M2/M4） — 🔶 一部完了（2026-08-18: pending→approved/rejected、approved→processing の条件 UPDATE；任意の二重審査 `withdraw.require_dual_review`；申請側の Redis ユーザーロック。Lua 限度額/一意制約なし）
12. **Webhook SSRF 遮断**: 内網/予約アドレスを拒否（M3） — ✅ 完了済み（2026-08-18: `isSafeWebhookUrl()` は https 公網のみ）
13. **ClickHouse の二者択一**: 実際に接続するか依存を削除 + ドキュメント修正（M6） — ⬜ 未実施
14. **デッドコード整理**: Vip/Achievement/Notification/FeatureFlag を接続または削除；Test model 削除；DepositLog 監査を永続化（M8） — 🔶 一部完了（2026-08-18: Test model 削除済み、DepositLog 監査を永続化；Vip/FeatureFlag/Notification は呼び出し元あり；AchievementService は EventConsumer が呼び出し）
15. **service テスト + CI ゲート**: コールバック署名検証/出金フロー/Redis ダウングレード/確率計算/楽観ロック並行の統合テスト；phpunit 失敗でブロック；service を CI に組み込み（現在は `|| echo warning` で失敗を許容） — 🔶 一部完了（service に WebhookUrlSafety / EventBusMessageFormat あり；CI `phpunit-service` job に組み込み、失敗でブロック）

**今回（2026-08-18）の追加完了項目（元の番号外）**:
- **テーブルプレフィックス修正**: 52 モデルからハードコード `erik_` プレフィックスを除去、`erik_erik_` 二重プレフィックス解消；DB プレフィックスは config/database.php `prefix=erik_` に統一、install.sql の変更不要
- **refresh token 書き直し**: service AuthController のトークンリフレッシュロジックを書き直し
- **DepositLogService の service 版移植**: service/common/service/DepositLogService.php を補完（admin/service 二重ドリフトの 1 つを解消）

### P2 — 可観測性 / 拡張 / 体験

16. **HarmonyOS C端** をゼロから 5 ページ実装（ログイン/ロビー/詳細/ウォレット/個人）（H4） — ✅ 完了済み（2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5 ページがリポジトリ内）
17. **フロントエンド補完**: 2FA 検証ページ、クーポン/ランキング/通知エントリ、ES 検索 UI；main.dart/app_pages.dart のルートソース統合；OAuth 実コールバック；フロントエンド AES 転送レイヤー
18. **確率計算を ClickHouse へ移行** または MySQL 物化統計テーブル + キャッシュ；リテンションを実コホートで再計算
19. **Prometheus 業務指標**（イベント配信/消費率、キューの深さ）+ グレースケール AB 分流ミドルウェア（FeatureFlag 再利用） — 🔶 一部完了（2026-08-18: `GET /metrics` に審査待ち出金/本日確定チャージ/イベント emit·consume カウント；FeatureFlag `inRollout`/`abTest` crc32 バケット。キューの深さは未実施）
20. **WebSocket データチェーンクローズドループ**: ランキング/チャットの永続化確認
21. **ドキュメント整合**: テーブル数/サービス数/共有レイヤー記述の修正、API ドキュメントと実装の整合、CHANGELOG 補完 — ✅ 完了済み（2026-08-18: `docs/CHANGELOG.md`、FEATURES/VERSIONS/PROJECT-PLAN/監査レポート §十 参照）

---

## 四、品質ゲート（チーム連携）

- コード変更のたび: admin 全量テスト `vendor/bin/phpunit` を必ず通す（`|| echo warning` を外す）
- 新規の機密パス（決済/出金/認証）にはテストを必ず添付
- common/model を変更する際は admin+service の両側で同期（共有レイヤー実装前）
- 審査レポートの重点推奨: ProviderAuth 署名、AES 暗号化、ProbabilityService 手書き SQL

## 五、チーム

game-platform チーム（6 名: researcher/architect/backend-dev/frontend-dev/tester/reviewer）準備完了、P0 を直接実行可能。
