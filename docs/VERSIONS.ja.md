# バージョン比較
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 総覧

| | 基礎版 (Lite) | 標準版 (Standard) | 完全版 (Full) |
|------|------|------|------|
| データテーブル (install.sql) | 19 | 29 | **66**（v1.3.15-22 で 22 テーブル追加） |
| API エンドポイント | 38 | 54 | ~260 (admin+service、Webhook/Provider 含む) |
| バックエンドコントローラー | 14 | 22 | admin 46 + service 35 |
| データモデル | 非共有 | 非共有 | **共有 52（platform-common）+ admin 8 + service 10** |
| 共有 Service | 共有レイヤーなし | 共有レイヤーなし | `packages/platform-common` 単一共有パッケージ |
| Admin フロントエンドページ | 11 | 13 | 15 |
| Platform フロントエンドページ | 8 | 10 | 10 |
| HarmonyOS (admin) | - | ログイン+ダッシュボード | **8 ページ** `admin/apps/harmonyos/` |
| HarmonyOS (C端) | - | - | **5 ページ** `apps/harmonyos/`（ログイン/ゲームロビー/詳細/ウォレット/マイ） |
| Docker サービス | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| テストケース | 60 | 60 | admin ~132；service 3 |

---

## ユーザー認証

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| ユーザー名パスワード登録/ログイン | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| クリック認証コード | stub | stub | ✓ poster-php |
| アカウントロック (5回/15分) | ✓ | ✓ | ✓ |
| セッション制限 (3並行) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7プラットフォーム (X/MS/LinkedIn/GitHub 含む) |
| 2FA TOTP 二要素認証 | - | - | ✓ |
| GDPR データエクスポート/アカウント削除 | - | - | ✓ |

---

## ウォレットと資金

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| プラットフォームコインウォレット | ✓ | ✓ | ✓ |
| ウォレット楽観ロック | ✓ | ✓ | ✓ |
| 流水記録 | ✓ | ✓ | ✓ |
| ゲームコインウォレット | ✓ | ✓ | ✓ |
| チャージ注文作成(作成時に checkout_url/expires_at を即時記録) | ✓ | ✓ | ✓ |
| チャージコールバック自動入金 | - | ✓ 手動 | ✓ Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook署名検証 |
| 交換見積/買い/売り | ✓ | ✓ | ✓ |
| 交換差益収益 | ✓ | ✓ | ✓ |
| 出金申請 | ✓ | ✓ | ✓ |
| グローバル出金スイッチ | ✓ | ✓ | ✓ |
| 出金審査 | ✓ 手動 | ✓ 手動 | ✓ 一括+手動 |
| KYC 段階別限度額 | - | ✓ 3級 | ✓ |
| 出金手数料 | - | - | ✓ |
| PDF 領収書 | - | - | ✓ |

---

## ゲーム管理

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| ゲーム CRUD | ✓ | ✓ | ✓ |
| ゲーム通貨管理 | ✓ | ✓ | ✓ |
| C端ゲーム一覧/詳細 | ✓ | ✓ | ✓ |
| ゲーム起動 | ✓ | ✓ | ✓ |
| ゲームカテゴリ (10種) | - | - | ✓ |
| カテゴリ絞り込み | - | - | ✓ |
| ゲーム区サーバー管理 | - | ✓ | ✓ |
| ゲーム記録トラッキング | - | ✓ | ✓ |
| ES 全文検索 | - | - | ✓ |
| 検索サジェスト | - | - | ✓ |
| サードパーティゲーム Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## 運用ツール

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| 公告管理 | ✓ | ✓ | ✓ |
| ダッシュボード | ✓ 管理バックエンド | ✓ 管理バックエンド | ✓ 管理+プラットフォーム |
| Excel エクスポート | ✓ | ✓ | ✓ |
| PDF エクスポート | ✓ | ✓ | ✓ |
| ダッシュボード実グラフ | - | - | ✓ fl_chart |
| クーポンシステム | - | - | ✓ |
| ランキング (日/週/月/総合) | - | - | ✓ Redisキャッシュ |
| WebSocket リアルタイムランキング | - | - | ✓ ポート8789 |
| 通知システム (サイト内+メール) | - | - | ✓ |
| 紹介報酬 | - | - | ✓ |
| 日次統計スナップショット | - | ✓ | ✓ |
| データレポート (集計/日報/CSV出力) | - | - | ✓ |
| C端プラットフォーム統計 | - | - | ✓ |
| プラットフォーム収益トラッキング | - | - | ✓ |

---

## セキュリティとコンプライアンス

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| 18層縦深防御 | ✓ | ✓ | ✓ |
| RBAC 権限制御 | ✓ | ✓ | ✓ |
| 操作監査ログ | ✓ | ✓ | ✓ |
| 8プラットフォーム送信元検出 | ✓ | ✓ | ✓ |
| Redis スライディングウィンドウレートリミット | ✓ | ✓ | ✓ |
| KYC 実名認証 | - | ✓ | ✓ |
| リスク管理エンジン (4ルール) | - | ✓ | ✓ |
| 決済コールバック署名検証 | - | - | ✓ |

---

## 国際化

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| 多言語サポート | 中/英語 | 4言語 | 4言語 |
| 翻訳テーブル+キャッシュ | ✓ | ✓ | ✓ |
| 言語自動検出 | ✓ | ✓ | ✓ |
| 国別差別化設定 | - | - | ✓ 8か国 |

---

## デプロイ運用

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| webman 単独デプロイ | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7サービス |
| Nginx リバースプロキシ | - | - | ✓ |
| CDN | - | - | ✓ 5社連携 + 管理画面設定/有効・無効/接続テスト（認証情報は暗号化、service は DB のみから読み取り） |
| Crontab 定期タスク | - | ✓ | ✓ |
| Prometheus モニタリング | ✓ | ✓ | ✓ `/metrics` 業務 gauge + イベント counter |
| ヘルスチェック | ✓ | ✓ | ✓ |
| hg/apidoc オンラインドキュメント | - | - | ✓ 41コントローラー |

---

## クライアント

| 機能 | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| Flutter Web PC 管理バックエンド | ✓ 5ページ | ✓ 11ページ | ✓ 17ページ |
| Flutter Web PC ユーザープラットフォーム | ✓ 5ページ | ✓ 8ページ | ✓ 10ページ |
| HarmonyOS admin | - | ✓ ログイン+ダッシュボード | ✓ 8ページ `admin/apps/harmonyos/` |
| HarmonyOS C端 | - | - | ✓ 5ページ `apps/harmonyos/` |

---

## データベーステーブル

### 基礎版 (19枚)
```
管理后台 (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

平台核心 (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### 標準版で追加 (10枚)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### 完全版で追加 (13枚)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

### v1.3.15-22 追加 (22テーブル)
```
game_event_outbox, game_reconciliation_batch, game_reconciliation_diff,
game_reconciliation_statement, game_device_fingerprint, game_device_account_map,
game_ip_reputation, game_account_account_link,
game_activity, game_activity_participation, game_activity_reward_log,
game_anticheat_event, game_anticheat_daily_stat,
game_group, game_group_member, game_share_link,
game_aml_rule, game_aml_hit, game_kyc_level, game_user_kyc, game_user_trust, game_risk_cluster
```

---

## API エンドポイント

| モジュール | 基礎版 | 標準版 | 完全版 |
|------|--------|--------|--------|
| 認証 | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| ウォレット | 2 | 2 | 3 (+チャージコールバック) |
| 交換 | 4 | 4 | 4 |
| 出金 | 2 | 2 | 8 (+一括+限度額+審査) |
| ゲーム | 3 | 4 | 7 (+区サーバー+記録+検索) |
| ユーザー | 2 | 2 | 7 (+KYC+GDPR+プライバシー) |
| 管理バックエンド | 18 | 25 | 79 |
| 運用ツール | - | - | 30 (+ランキング+クーポン+通知+紹介) |
| 国際化 | 2 | 2 | 4 (+国別設定) |
| **合計** | **38** | **54** | **~260** |

---

## エコシステム拡張 (v2.0) — 追加

| 機能 | 説明 |
|------|------|
| GameProvider 抽象レイヤー | SelfProvider (DBトランザクション) + ThirdPartyProvider (HTTP+署名) |
| Provider API ゲートウェイ | balance/bet/settle/refund コールバック + ProviderAuth 中間件 |
| チケットシステム | C端作成/返信 + 管理端処理/割り当て/クローズ |
| メール検証 | 6桁認証コード、Redis 10分で失効、60秒再送制限 |
| プッシュ通知 | PushService (FCM/APNs/华为推送) |
| VIP 体系 | 5級、経験値累積、自動昇格、交換割引、出金減免、レートボーナス |
| アチーブメントシステム | 12個の内蔵アチーブメント、イベント駆動検出、進捗トラッキング |
| フレンドシステム | 申請/承認/拒否/削除/検索 |
| ダイレクトメッセージ/チャット | REST + WebSocket リアルタイムメッセージ (ポート8790) |
| イベントバス | Redis Pub/Sub；emit INCR `metrics:event_*`；消費プロセス `EventConsumer` 実装済み |
| フィーチャーフラグ | FeatureFlag は DB ベース；`inRollout`/`abTest` は `feature.{name}_percent` を参照 |
| Webhook | - | - | ✓ 7種イベント+Pub/Sub配信 |
| チャット | - | - | ✓ REST+WebSocket :8791 |
| トーナメントシステム | - | - | ✓ FeatureFlag+tournament |
| クーポン条件 | - | - | ✓ min_deposit/first_user/game_id |
| 多段階報酬 | - | - | ✓ 二級分潤 |
| SDKドキュメント | - | - | ✓ PHP/Go/Python |
| 高度な分析 | リテンション/D1-D30、コンバージョンファネル、ARPU/ARPPU |

### 追加データテーブル (10枚)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### 追加 Provider API エンドポイント (4個)
```
POST /api/provider/balance  — 查询余额
POST /api/provider/bet      — 通知下注
POST /api/provider/settle   — 通知结算
POST /api/provider/refund   — 通知退款
```

### 追加 C端 API エンドポイント (8個)
```
POST /api/verify/send-email    — 发送邮箱验证码
POST /api/verify/confirm-email — 确认邮箱
GET  /api/ticket/list             — 工单列表
POST /api/ticket/create           — 创建工单
GET  /api/ticket/{id}             — 工单详情
POST /api/ticket/{id}/reply       — 回复工单
GET  /api/user/vip-status         — VIP状态
GET  /api/user/achievements       — 成就列表
```

### 追加管理バックエンド API エンドポイント (6個)
```
GET  /admin/ticket/list          — 工单列表
GET  /admin/ticket/{id}          — 工单详情
POST /admin/ticket/{id}/reply    — 回复工单
POST /admin/ticket/{id}/close    — 关闭工单
POST /admin/ticket/{id}/assign   — 指定处理人
GET  /admin/analytics/retention  — 留存分析
GET  /admin/analytics/funnel     — 转化漏斗
GET  /admin/analytics/arpu       — ARPU趋势
GET  /admin/analytics/economy    — 经济指标
```
