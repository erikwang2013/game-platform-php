# 機能ドキュメント
<!-- lang-nav -->

Languages: **中文** · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 機能総覧

### 基礎版 (MVP) — 完了

| ドメイン | 機能 | 状態 |
|----|------|------|
| ユーザー | 登録/ログイン/JWT/認証コード | 完了 |
| ウォレット | プラットフォームコイン残高/明細照会 | 完了 |
| チャージ | チャージ注文の作成（Stripe 125+ ローカル決済、Alipay/WeChat Pay APM を含む / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal コールバック） | 完了 |
| 交換 | プラットフォームコイン⇄ゲームコイン（固定レート+差額） | 完了 |
| 出金 | 申請/照会/グローバルスイッチ/自動審査/人工審査 | 完了 |
| ゲーム | バックエンドCRUD/通貨管理/C端リスト/詳細/起動 | 完了 |
| 管理 | ゲーム管理/出金審査/ユーザー管理/決済管理/お知らせ管理 | 完了 |
| パネル | プラットフォームダッシュボード（DAU/明細/収益/ランキング） | 完了 |
| エクスポート | Excelエクスポート ユーザー/明細/出金 | 完了 |
| 国際化 | 中/英切替、翻訳テーブル、言語検出ミドルウェア | 完了 |
| フロントエンド | Flutter PC管理バックエンド + C端ユーザープラットフォーム（i18n含む） | 完了 |

### 標準版 — 完了

| ドメイン | 機能 | 状態 |
|----|------|------|
| ユーザー | OAuthログイン (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | 完了 |
| 決済 | 複数決済チャネルの自動コールバック (Stripe Alipay/WeChat Pay APM 含む / PayPal / NOWPayments IPN / Coinbase Webhook) | 完了 |
| ゲーム | 区サーバー管理、ゲーム記録の追跡 | 完了 |
| 出金 | KYC段階別限度額 (default/verified/vip) + 手数料 | 完了 |
| KYC | 実名認証の申請+審査 | 完了 |
| リスク管理 | IPブラックリスト/大額警告/頻度/速度検知 | 完了 |
| 統計 | 日次統計スナップショット (ユーザー/チャージ/出金/交換/ゲーム) | 完了 |
| フロントエンド | Admin: KYC審査+リスクログ / Platform: OAuth+KYC+ゲーム記録 | 完了 |

### 完全版 — 完了

| ドメイン | 機能 | 状態 |
|----|------|------|
| ゲームロビー | 10個のプリセット分類、分類フィルター、ゲーム-分類関連付け | 完了 |
| ランキング | 日榜/週榜/月榜/総榜、Redisキャッシュ、複数指標 | 完了 |
| クーポン | 固定額+比率割引、期限・数量限定、受取/利用の追跡 | 完了 |
| 国別設定 | 8ヶ国プリセット、差別化された決済/出金方法、最低チャージ額 | 完了 |
| 統計 | 日次統計スナップショット + プラットフォーム収益追跡 | 完了 |
| 検索 | Elasticsearch 全文検索（モデル層に統合済み） | 完了 |

### プロダクションレベルアップグレード — 完了

| ドメイン | 機能 | 状態 |
|----|------|------|
| OAuth | Google/Facebook/Apple 実トークン交換 | 完了 |
| 決済 | コールバック署名検証 (Stripe Webhook Alipay/WeChat Pay APM 含む、PayPal Webhook、NOWPayments IPN HMAC-SHA512、Coinbase HMAC-SHA256 base64 secret) | 完了 |
| 認証コード | poster-php クリック式認証コード | 完了 |
| 通知 | サイト内メッセージ + メール、チャージ/出金/KYC/クーポン自動通知 | 完了 |
| 2FA | Google Authenticator TOTP + 予備リカバリーコード | 完了 |
| 紹介 | 紹介コード、登録報酬、チャージコミッション | 完了 |
| 検索 | ES検索API + ゲーム提案 + LIKEフォールバック | 完了 |
| ランキング | WebSocket リアルタイムプッシュ (ポート8789) | 完了 |
| CDN | 5社連携 (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS アップロード + キャッシュ削除 + プリロード) | 完了 |
| CDN 管理 | 管理画面で5社設定 (暗号化保存した認証情報/有効·無効/HeadBucket 接続テスト)、service は DB のみ参照 | 完了 |
| レポート | 管理側データレポート（集計/日報/CSV エクスポート、Redis 5分キャッシュ、期間 ≤90日） | 完了 |
| プラットフォーム統計 | C端ホーム統計（ゲーム総数/ユーザー総数/今日の対局/7日間アクティブ） | 完了 |
| デプロイ | Docker Compose 7サービス + Nginxリバースプロキシ | 完了 |
| データ | MySQL リアルタイム集計分析 + 結合/条件確率計算 | 完了 |
| HarmonyOS | admin 端 8 ページ；C 端 `apps/harmonyos/` にログイン/ロビー/詳細/ウォレット/マイページ実装（8788 を指す） | 一部完了（工程は実行可能、実機では IP 変更が必要） |
| API ドキュメント | hg/apidoc インタラクティブドキュメント | 完了 |
| ワンクリックインストール | ブラウザインストールウィザード：管理者作成、既存DBアップグレード、install.lock で再インストール防止 | 完了 |
| 耐障害性 | CircuitBreaker 遮断 + Retry 再試行 + feature.provider_mock 縮退スイッチ | 完了 |
| 決済手段 | 管理CRUD + 国別表示 + 金額範囲 + 通貨制限 | 完了 |
| CI | push 時に自動インクリメント tag + GitHub Release | 完了 |

### エコシステム拡張 (v2.0) — 完了

| ドメイン | 機能 | 状態 |
|----|------|------|
| ゲーム接続 | GameProvider 抽象層 (Self/ThirdParty) + HMAC-SHA256 署名 | 完了 |
| ゲームコールバック | Provider API ゲートウェイ (balance/bet/settle/refund) + ProviderAuth ミドルウェア | 完了 |
| ゲームセッション | Redis ハートビート + 15分タイムアウト自動決済 + GameSessionService | 完了 |
| チケットシステム | C端作成/返信 + 管理端処理/割当/クローズ、5種のチケットタイプ | 完了 |
| メール検証 | 6桁認証コード、Redis 10分期限切れ、60秒再送制限 | 完了 |
| プッシュ通知 | PushService (FCM/APNs/華為プッシュ) + DeviceToken モデル | 完了 |
| VIP 体系 | 5段階 (普通/白銀/黄金/白金/钻石) + 経験値 + 自動昇格 | 完了 |
| VIP 特典 | 交換割引 2-15%、出金手数料免除 10-100%、レート加成 0.1-1.0% | 完了 |
| 成就システム | 12個の内蔵成就；EventConsumer → AchievementService イベント駆動検知と VIP 経験値 | 完了 |
| フレンドシステム | 申請/承認/拒否/削除/検索、pending/accepted/blocked 状態 | 完了 |
| 私信/チャット | REST 私信 + WebSocket リアルタイムメッセージ (ポート8790)、友人のみ送信可 | 完了 |
| イベントバス | Redis Pub/Sub；emit + EventConsumer 消費成就/Webhook + metrics INCR | 完了 |
| フィーチャーフラグ | FeatureFlag DBベース；`inRollout`/`abTest` crc32 分桶で `feature.{name}_percent` を読む | 完了 |
| 高度な分析 | リテンション/D1-D30、コンバージョンファネル、ARPU/ARPPU、ゲーム通貨経済指標 (MySQL リアルタイム集計) | 完了 |
| Webhook | 订阅管理 + Redis Pub/Sub イベント配信、7種のイベントを選択可 | 完了 |
| 聊天 | REST 私信 + WebSocket リアルタイムメッセージ (ポート8791)、友人のみ送信可 | 完了 |
| トーナメント | 作成/list/detail/join、FeatureFlagスイッチ、ランキング、人数上限 | 完了 |
| 多段階コミッション | 二段階紹介分与、ReferralCommission モデル、設定可能なコミッション率 | 完了 |
| クーポン条件 | min_deposit/first_user_only/game_id 3種の条件制限 | 完了 |
| SDK ドキュメント | Provider 接続ドキュメント (PHP/Go/Python 例 + 4 API エンドポイント) | 完了 |
| ミニゲーム | Farm Match-3 P0（ドメインエンジン + 4レベル設計、TypeScript/Vite/Vitest 単体テスト） | 完了 |

## 2. C端ユーザー機能

### 2.1 ユーザージャーニー

```
登録 → ログイン → メール/携帯検証 → ゲームロビー閲覧 → ゲーム詳細へ
                                           ↓
ウォレット確認 ← ゲームプレイ ← ゲームコイン交換 (VIP割引) ← プラットフォームコインのチャージ
    ↓
  出金 (VIP手数料免除) → バックエンド審査 → 入金
    ↓
フレンドシステム → 私信チャット → ランキング競技 → 成就追跡
    ↓
チケットサポート
```

### 2.2 API エンドポイント

| メソッド | パス | 説明 | 認証 |
|------|------|------|------|
| POST | /api/auth/register | ユーザー登録 | なし |
| POST | /api/auth/login | ユーザーログイン | なし |
| POST | /api/auth/refresh | Token更新 | なし |
| GET | /api/game/list | ゲーム一覧 | なし |
| GET | /api/game/detail/{id} | ゲーム詳細 | なし |
| GET | /api/announcement/list | お知らせ一覧 | なし |
| GET | /api/wallet/info | ウォレット残高 | あり |
| GET | /api/wallet/transactions | 明細記録 | あり |
| POST | /api/deposit/create | チャージ注文の作成 | あり |
| GET | /api/payment/methods | 決済手段一覧（国別ルーティング） | あり |
| POST | /api/exchange/quote | 交換の見積り (VIP割引) | あり |
| POST | /api/exchange/buy | ゲームコインの購入 | あり |
| POST | /api/exchange/sell | ゲームコインの売却 | あり |
| POST | /api/withdraw/apply | 出金申請 (VIP免除) | あり |
| POST | /api/game/launch | ゲーム起動 | あり |
| GET | /api/game/play-logs | ゲーム記録 | あり |
| POST | /api/referral/apply | 紹介コードの利用 | あり |
| POST | /api/verify/send-email | メール認証コード送信 | あり |
| POST | /api/verify/confirm-email | メール確認 | あり |
| GET | /api/ticket/list | チケット一覧 | あり |
| POST | /api/ticket/create | チケット作成 | あり |
| POST | /api/ticket/{id}/reply | チケット返信 | あり |

| GET | /api/platform/stats | プラットフォーム統計 | なし |
## 3. 管理バックエンド機能

### 3.1 API エンドポイント（追加）

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/dashboard/platform | プラットフォームダッシュボードデータ |
| GET | /admin/analytics/overview | プラットフォーム総覧 (MySQL リアルタイム集計) |
| GET | /admin/analytics/game-ranking | ゲームランキング |
| GET | /admin/analytics/dau-trend | DAU トレンド |
| GET | /admin/analytics/hourly-trend | 時間別トレンド |
| GET | /admin/analytics/action-distribution | 行動分布 |
| GET | /admin/analytics/revenue | 収益分析 |
| GET | /admin/analytics/conversion | ゲーム転換率 |
| GET | /admin/analytics/probability | 結合/条件確率 |
| GET | /admin/analytics/retention | リテンション分析 D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | 転換ファネル |
| GET | /admin/analytics/arpu | ARPU/ARPPU トレンド |
| GET | /admin/analytics/economy | ゲーム通貨経済指標 |
| GET | /admin/report/summary | レポート集計（新規ユーザー/入金/出金/両替/ゲーム対局数） |
| GET | /admin/report/daily | 日報（日次集計、データのない日は 0 補完） |
| GET | /admin/report/export | 日報 CSV エクスポート（UTF-8 BOM） |
| GET | /admin/game/list | ゲーム一覧 |
| POST | /admin/game/create | ゲーム作成 (provider_config 含む) |
| PUT | /admin/game/{id} | ゲーム編集 |
| GET | /admin/withdraw/orders | 出金注文一覧 |
| PUT | /admin/withdraw/review | 出金審査 |
| GET | /admin/ticket/list | チケット一覧 |
| GET | /admin/ticket/{id} | チケット詳細 |
| POST | /admin/ticket/{id}/reply | チケット返信 |
| POST | /admin/ticket/{id}/close | チケットクローズ |
| POST | /admin/ticket/{id}/assign | 処理担当者の指定 |

## 4. Provider API（ゲーム側コールバック）

| メソッド | パス | 説明 | 認証 |
|------|------|------|------|
| POST | /api/provider/balance | ユーザー残高照会 | HMAC-SHA256 |
| POST | /api/provider/bet | 下注通知 | HMAC-SHA256 |
| POST | /api/provider/settle | 決済通知 | HMAC-SHA256 |
| POST | /api/provider/refund | 返金通知 | HMAC-SHA256 |

署名アルゴリズム: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
リクエストヘッダー: `X-Game-Id` + `X-Timestamp` + `X-Signature`
時間ウィンドウ: 5分

## 5. VIP 体系

| ランク | 累計EXP | 交換割引 | 出金手数料免除 | レート加成 |
|------|---------|---------|-------------|---------|
| 普通 | 0 | 0% | 0% | 基準 |
| 白銀 | 500 | 2% | 10% | +0.1% |
| 黄金 | 2,500 | 5% | 30% | +0.3% |
| 白金 | 12,500 | 10% | 50% | +0.5% |
| 钻石 | 62,500 | 15% | 100% | +1.0% |

### 経験値の獲得

| 行動 | EXP |
|------|-----|
| 1元チャージ | 10 |
| デイリーログイン | 5 |
| KYC完了 | 50 |
| 新規ユーザー招待 | 100 |
| 成就達成 | 10-100 |

## 6. 成就一覧

| 成就 | 条件 | ポイント |
|------|------|------|
| First Deposit | 初回チャージ | 20 |
| Century Club | 累計チャージ100 | 50 |
| High Roller | 累計チャージ1000 | 100 |
| Trader | 初回交換 | 20 |
| Day Trader | 累計交換100回 | 100 |
| Explorer | 3ゲームプレイ | 30 |
| Adventurer | 5ゲームプレイ | 50 |
| Conqueror | 10ゲームプレイ | 100 |
| Weekly Warrior | 7日連続ログイン | 30 |
| Monthly Master | 30日連続ログイン | 100 |
| Connector | 友人を1人招待 | 30 |
| Influencer | 友人を10人招待 | 100 |

## 7. データベーステーブル一覧

### エコシステム拡張で追加 (10枚)

| テーブル名 | 説明 | 主要特性 |
|------|------|---------|
| game_ticket | 工单 | user_id+type+status インデックス, assigned_to |
| game_ticket_reply | 工单返信 | ticket_id インデックス, is_admin 区分 |
| game_device_token | デバイストークン | user_id+platform+token 一意インデックス |
| game_vip_level | VIPレベル定義 | level 一意インデックス, benefits JSON |
| game_user_vip | ユーザーVIP記録 | user_id 一意インデックス, level+exp+total_exp |
| game_exp_log | 経験値ログ | user_id+source 複合インデックス |
| game_achievement | 成就定義 | key 一意インデックス, condition_json JSON |
| game_user_achievement | ユーザー成就 | user_id+achievement_id 一意インデックス |
| game_friend | フレンド関係 | user_id+friend_id 一意インデックス |
| game_message | 私信 | from_user_id+to_user_id / to_user_id+is_read |

### テーブル構造の変更

| テーブル名 | 変更 |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**合計: install.sql 43 枚のテーブル**（エコシステム拡張の 10 枚は `install/` にあり、install.sql には未統合）。モデルは非共有：admin 46 / service 44 各1部。

## 8. テストカバレッジ

| テストファイル | ケース数 | カバレッジ範囲 |
|---------|--------|---------|
| PlatformTest | 56 | bcmath精度/交換計算/出金手数料/限度額/リスク管理/クーポン/KYC/i18n |
| BackendEnhancementTest | 23 | 暗号化サービス/Hashids/Snowflake |
| CaptchaTest | 7 | 認証コードの生成/検証 |
| EncryptionServiceTest | 6 | AES暗号化/復号/マスキング |
| EnvConfigTest | 4 | 環境変数設定 |
| HashidsServiceTest | 8 | IDエンコード/デコード往復 |
| SnowflakeServiceTest | 6 | ID生成の一意性 |

**合計: admin ~132 ケース / 8 ファイル；service 3 ケース（WebhookUrlSafety + EventBusMessageFormat）。service は CI 失敗のブロッカーには含まれない。**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
